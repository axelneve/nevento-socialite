@props(['label' => 'Melding of wens', 'floating' => true])
{{--
    Report a bug or ask for a feature: a floating button, a dialog and the form, in one self-contained piece (plain CSS and
    JS, no Alpine or Tailwind needed). Posts to the package's proxy route, which adds who and where and forwards it to
    nevento-system. Renders nothing until SYSTEM_TOKEN is set and a user is logged in.

    Put it once in the app's layout, before </body>:   <x-nevento::report-button />
    With :floating="false" there is no floating button; any element with the attribute data-nevento-report opens the dialog.
--}}
@if (filled(config('nevento-system.token')) && auth()->check())
    @php $result = session('nevento_report'); @endphp
    <div class="nvr" data-nvr>
        <style>
            .nvr, .nvr * { box-sizing: border-box; }
            .nvr { font: 14px/1.4 system-ui, -apple-system, "Segoe UI", sans-serif; color: #1f2937; }
            .nvr-open { position: fixed; right: 16px; bottom: 16px; z-index: 2147483000; display: inline-flex; align-items: center; gap: 6px; padding: 10px 14px; border: 0; border-radius: 999px; background: #1f2937; color: #fff; font: inherit; font-weight: 600; cursor: pointer; box-shadow: 0 4px 14px rgba(0,0,0,.25); }
            .nvr-open:hover { background: #111827; }
            .nvr-dialog { width: min(560px, calc(100vw - 24px)); max-height: calc(100vh - 24px); padding: 0; border: 0; border-radius: 14px; box-shadow: 0 20px 50px rgba(0,0,0,.35); }
            .nvr-dialog::backdrop { background: rgba(17,24,39,.5); }
            .nvr-form { display: grid; gap: 12px; padding: 20px; }
            .nvr-form h2 { margin: 0; font-size: 18px; }
            .nvr-types { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
            .nvr-types label { display: block; padding: 10px; text-align: center; font-weight: 600; border: 2px solid #e5e7eb; border-radius: 10px; cursor: pointer; }
            .nvr-types input { position: absolute; opacity: 0; pointer-events: none; }
            .nvr-types input:checked + span { color: #111827; }
            .nvr-types label:has(input:checked) { border-color: #1f2937; background: #f3f4f6; }
            .nvr-form label.nvr-field { display: grid; gap: 4px; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #6b7280; }
            .nvr-form input[type=text], .nvr-form textarea, .nvr-form select { width: 100%; padding: 8px 10px; font: inherit; font-weight: 400; letter-spacing: 0; text-transform: none; color: #111827; background: #fff; border: 1px solid #d1d5db; border-radius: 8px; }
            .nvr-shot { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
            .nvr-shot input[type=file] { flex: 1; min-width: 0; font: inherit; font-size: 13px; }
            .nvr-btn { padding: 8px 14px; font: inherit; font-weight: 600; color: #374151; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; }
            .nvr-btn-primary { color: #fff; background: #1f2937; border-color: #1f2937; }
            .nvr-actions { display: flex; justify-content: flex-end; gap: 8px; }
            .nvr-note { margin: 0; font-size: 12px; color: #6b7280; }
            .nvr-toast { position: fixed; right: 16px; bottom: 70px; z-index: 2147483000; max-width: 320px; padding: 10px 14px; color: #fff; background: #166534; border-radius: 10px; box-shadow: 0 4px 14px rgba(0,0,0,.25); }
            .nvr-toast.nvr-bad { background: #991b1b; }
            .nvr [hidden] { display: none !important; }
        </style>

        @if ($floating)
            <button type="button" class="nvr-open" data-nevento-report>{{ $label }}</button>
        @endif

        @if (is_array($result))
            <div class="nvr-toast {{ $result['ok'] ? '' : 'nvr-bad' }}" role="status" data-nvr-toast>{{ $result['message'] }}</div>
        @endif

        <dialog class="nvr-dialog" data-nvr-dialog aria-label="{{ $label }}">
            <form method="POST" action="{{ route('nevento.report') }}" enctype="multipart/form-data" class="nvr-form" data-nvr-form>
                @csrf
                <h2>{{ $label }}</h2>

                <div class="nvr-types">
                    <label><input type="radio" name="type" value="bug" checked><span>Bug melden</span></label>
                    <label><input type="radio" name="type" value="feature_request"><span>Wens indienen</span></label>
                </div>

                <label class="nvr-field">Korte samenvatting
                    <input type="text" name="summary" maxlength="200" required>
                </label>
                <label class="nvr-field"><span data-nvr-desc-label>Wat ging er mis?</span>
                    <textarea name="description" rows="4" maxlength="5000" required></textarea>
                </label>
                <label class="nvr-field" data-nvr-bug-only>Stappen om het te herhalen (optioneel)
                    <textarea name="steps" rows="3" maxlength="5000"></textarea>
                </label>
                <label class="nvr-field"><span data-nvr-sev-label>Impact</span>
                    <select name="severity">
                        <option value="low">Laag</option>
                        <option value="medium" selected>Normaal</option>
                        <option value="high">Hoog</option>
                        <option value="critical">Kritiek</option>
                    </select>
                </label>

                <div>
                    <div class="nvr-field" style="margin-bottom:4px">Screenshot (optioneel)</div>
                    <div class="nvr-shot">
                        <input type="file" name="screenshot" accept="image/png,image/jpeg,image/webp" data-nvr-file>
                        <button type="button" class="nvr-btn" data-nvr-capture>Maak screenshot</button>
                    </div>
                    <p class="nvr-note" data-nvr-shot-status>PNG, JPG of WebP tot 5 MB.</p>
                </div>

                @foreach (['current_url', 'route_name', 'page_title', 'viewport', 'screen', 'language', 'timezone', 'referrer', 'timestamp', 'online'] as $key)
                    <input type="hidden" name="context[{{ $key }}]" data-nvr-ctx="{{ $key }}">
                @endforeach

                <p class="nvr-note">Pagina, browser en jouw naam en e-mail gaan automatisch mee.</p>
                <div class="nvr-actions">
                    <button type="button" class="nvr-btn" data-nvr-close>Annuleren</button>
                    <button type="submit" class="nvr-btn nvr-btn-primary" data-nvr-submit>Versturen</button>
                </div>
            </form>
        </dialog>

        <script>
            (function () {
                var root = document.currentScript.closest('[data-nvr]');
                if (!root || root.dataset.nvrReady) return;
                root.dataset.nvrReady = '1';
                var dialog = root.querySelector('[data-nvr-dialog]');
                var form = root.querySelector('[data-nvr-form]');
                var status = root.querySelector('[data-nvr-shot-status]');
                var routeName = @json(optional(request()->route())->getName());

                function ctx() {
                    var values = {
                        current_url: location.href, route_name: routeName, page_title: document.title,
                        viewport: innerWidth + 'x' + innerHeight, screen: screen.width + 'x' + screen.height,
                        language: navigator.language, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                        referrer: document.referrer, timestamp: new Date().toISOString(), online: String(navigator.onLine)
                    };
                    root.querySelectorAll('[data-nvr-ctx]').forEach(function (el) { el.value = values[el.dataset.nvrCtx] || ''; });
                }

                function sync() {
                    var bug = form.elements.type.value === 'bug';
                    root.querySelector('[data-nvr-bug-only]').hidden = !bug;
                    root.querySelector('[data-nvr-desc-label]').textContent = bug ? 'Wat ging er mis?' : 'Wat zou je willen?';
                    root.querySelector('[data-nvr-sev-label]').textContent = bug ? 'Impact' : 'Prioriteit';
                }

                document.addEventListener('click', function (event) {
                    if (event.target.closest('[data-nevento-report]') && !dialog.open) { event.preventDefault(); ctx(); sync(); dialog.showModal(); }
                });
                root.querySelector('[data-nvr-close]').addEventListener('click', function () { dialog.close(); });
                form.addEventListener('change', sync);
                form.addEventListener('submit', function () { root.querySelector('[data-nvr-submit]').disabled = true; });

                var toast = root.querySelector('[data-nvr-toast]');
                if (toast) setTimeout(function () { toast.remove(); }, 8000);

                // Screenshot of the page behind the dialog. html-to-image is loaded only when asked for; if it cannot
                // load (offline, a strict CSP) or fails, the file input above is the way.
                root.querySelector('[data-nvr-capture]').addEventListener('click', async function () {
                    status.textContent = 'Screenshot maken…';
                    try {
                        var lib = await import('https://cdn.jsdelivr.net/npm/html-to-image@1.11.13/+esm');
                        dialog.close();
                        await new Promise(function (r) { requestAnimationFrame(function () { requestAnimationFrame(r); }); });
                        var blob = await lib.toBlob(document.body, {
                            cacheBust: false, skipFonts: true, pixelRatio: Math.min(devicePixelRatio || 1, 2),
                            backgroundColor: getComputedStyle(document.body).backgroundColor || '#ffffff',
                            filter: function (node) { return !(node instanceof Element) || !node.closest('[data-nvr]'); }
                        });
                        dialog.showModal();
                        if (!blob) throw new Error('empty');
                        var transfer = new DataTransfer();
                        transfer.items.add(new File([blob], 'screenshot-' + Date.now() + '.png', { type: 'image/png' }));
                        root.querySelector('[data-nvr-file]').files = transfer.files;
                        status.textContent = 'Screenshot toegevoegd.';
                    } catch (e) {
                        if (!dialog.open) dialog.showModal();
                        status.textContent = 'Screenshot maken is niet gelukt. Voeg zo nodig zelf een afbeelding toe.';
                    }
                });
            })();
        </script>
    </div>
@endif
