<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Contracts;

/**
 * Implemented by an app that keeps a read-only copy of the people register
 * (nevento-idp, `.claude/specs/personen-register.md`). The package owns the route and
 * the auth; the app supplies the storing.
 *
 * Bind an implementation in the container:
 *   $this->app->bind(SyncsPeople::class, YourPeopleSync::class);
 *
 * Until it is bound, `POST /internal/people/changed` answers 404, which the IDP treats as
 * "this app does not take part yet".
 */
interface SyncsPeople
{
    /**
     * People changed at the IDP. Fetch them with {@see \EventSolutions\NeventoSocialite\PeopleClient}
     * and write the app's local copy. This is the ONLY place the copy may be written: it is
     * read-only everywhere else.
     *
     * Must be safe to call twice and for uuids that no longer exist. Initialise the app's own
     * tenant for $workspaceId first, if it is tenant-based: the push carries only ids.
     *
     * @param  list<string>  $uuids  the people that changed; empty means "unknown, refresh everything"
     */
    public function sync(int $workspaceId, array $uuids): void;
}
