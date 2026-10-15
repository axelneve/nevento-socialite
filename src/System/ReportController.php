<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\System;

use EventSolutions\NeventoSocialite\NeventoContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The proxy behind `<x-nevento::report-button>`: takes the form the user filled in, adds who they are (from the IDP
 * session), where they were and what they use, and forwards it to nevento-system. The app never sees the system's token.
 */
class ReportController
{
    public function store(Request $request, SystemClient $system): RedirectResponse|JsonResponse
    {
        abort_unless($system->enabled(), 404);

        $data = $request->validate([
            'type' => ['required', 'in:bug,feature_request'],
            'summary' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'steps' => ['nullable', 'string', 'max:5000'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'feature_area' => ['nullable', 'string', 'max:60'],
            'context' => ['nullable', 'array'],
            'context.*' => ['nullable', 'string', 'max:2000'],
            'screenshot' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'], // the system takes about 6 MB of image as a data URL
        ]);

        $user = NeventoContext::user();
        $workspace = NeventoContext::workspace();
        $client = array_filter((array) ($data['context'] ?? []), fn ($value) => filled($value));

        $sent = $system->report(array_filter([
            // A fresh id per submit: a retried request (double click, timeout) updates instead of duplicating.
            'source_ref' => (string) Str::uuid(),
            'workspace' => isset($workspace['id']) ? (string) $workspace['id'] : null,
            'reporter_name' => $user['name'] ?? $request->user()?->name,
            'reporter_email' => $user['email'] ?? $request->user()?->email,
            'reporter_idp_id' => isset($user['id']) ? (string) $user['id'] : null,
            'reporter_role' => NeventoContext::role(),
            'type' => $data['type'],
            'summary' => trim($data['summary']),
            'description' => trim($data['description']),
            'steps' => filled($data['steps'] ?? null) ? trim($data['steps']) : null,
            'severity' => $data['severity'] ?? null,
            'feature_area' => $data['feature_area'] ?? null,
            'page_url' => $client['current_url'] ?? null,
            'route_name' => $client['route_name'] ?? null,
            'user_agent' => $request->userAgent(),
            'context' => ['client' => $client, 'server' => array_filter([
                'app_env' => app()->environment(),
                'app_url' => config('app.url'),
                'workspace_name' => $workspace['name'] ?? null,
                'roles' => NeventoContext::roles(),
            ])],
            'screenshot' => $this->dataUrl($request),
        ], fn ($value) => $value !== null && $value !== []));

        $result = $sent
            ? ['ok' => true, 'message' => $data['type'] === 'bug' ? 'Bedankt, de melding is verstuurd.' : 'Bedankt, je wens is verstuurd.']
            : ['ok' => false, 'message' => 'Versturen is nu niet gelukt. Probeer het zo nog eens.'];

        return $request->expectsJson()
            ? response()->json($result, $sent ? 201 : 502)
            : back()->with('nevento_report', $result);
    }

    private function dataUrl(Request $request): ?string
    {
        $file = $request->file('screenshot');

        if ($file === null) {
            return null;
        }

        $type = $file->getMimeType() === 'image/jpeg' ? 'jpeg' : ($file->getMimeType() === 'image/webp' ? 'webp' : 'png');

        return "data:image/{$type};base64,".base64_encode((string) file_get_contents($file->getRealPath()));
    }
}
