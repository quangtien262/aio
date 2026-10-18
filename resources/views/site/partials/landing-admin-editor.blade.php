@php
    $canEditLanding = true;
    $hero = [];
    $heroSlides = [];
@endphp

@include('theme-foot403::partials.inline-editor-styles')

@unless($hasButtons)
    <aside class="aio-landing-editor-toolbar" aria-label="Chỉnh sửa nhanh landing page">
        <details open>
            <summary title="Thu gọn / mở rộng danh sách khối">
                <strong>Chỉnh sửa trang</strong>
                <svg class="aio-landing-editor-toolbar-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </summary>
            <div class="aio-landing-editor-toolbar-content" data-reorder-url="{{ route('admin.api.landing.pages.blocks.reorder', ['landingPage' => data_get(collect($landingBlocks)->first(), 'landing_page_id')]) }}">
                @foreach($landingBlocks as $block)
                    <div class="aio-landing-editor-row" data-sort-block-id="{{ data_get($block, 'id') }}">
                        <button type="button" class="aio-landing-editor-drag" aria-label="Sắp xếp {{ data_get($block, 'data.title', data_get($block, 'block_type')) }}" title="Kéo để sắp xếp hoặc dùng phím ↑ / ↓">⠿</button>
                        <button type="button" data-xd-edit-block="{{ data_get($block, 'id') }}">
                            Sửa khối <span>{{ data_get($block, 'data.title', data_get($block, 'label', data_get($block, 'block_type'))) }}</span>
                        </button>
                    </div>
                @endforeach
            </div>
            <p class="aio-landing-editor-sort-status" role="status" aria-live="polite"></p>
        </details>
    </aside>
    <script>
        (() => {
            const menu = document.querySelector('.aio-landing-editor-toolbar details');
            if (!menu) return;

            const storageKey = 'aio.landing-editor-toolbar.open';
            try {
                const savedState = localStorage.getItem(storageKey);
                if (savedState !== null) menu.open = savedState === 'true';
            } catch (_) {}

            menu.addEventListener('toggle', () => {
                try {
                    localStorage.setItem(storageKey, String(menu.open));
                } catch (_) {}
            });
        })();
    </script>
    @include('site.partials.landing-admin-sort')
@endunless

@unless($hasEditor)
    @include('theme-xd0302::partials.inline-editor')
@endunless

<style>
    .aio-landing-editor-toolbar{position:fixed;right:18px;top:18px;z-index:110;display:grid;gap:9px;width:min(290px,calc(100vw - 36px));max-height:calc(100vh - 36px);overflow:auto;padding:12px;border:1px solid rgba(255,255,255,.4);border-radius:16px;background:rgba(15,23,42,.94);color:#fff;font:600 13px/1.4 system-ui,sans-serif;box-shadow:0 18px 50px rgba(0,0,0,.35);backdrop-filter:blur(12px)}
    .aio-landing-editor-toolbar summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:3px 4px;color:#f8fafc;font-size:14px;list-style:none;cursor:pointer;user-select:none}
    .aio-landing-editor-toolbar summary::-webkit-details-marker{display:none}.aio-landing-editor-toolbar summary:focus-visible{outline:2px solid #fff;outline-offset:4px;border-radius:4px}
    .aio-landing-editor-toolbar-chevron{flex-shrink:0;transition:transform .15s ease}.aio-landing-editor-toolbar details[open] .aio-landing-editor-toolbar-chevron{transform:rotate(180deg)}
    .aio-landing-editor-toolbar-content{display:grid;gap:6px;margin-top:9px}
    .aio-landing-editor-toolbar button{display:grid;grid-template-columns:64px minmax(0,1fr);align-items:center;gap:9px;min-height:38px;border:1px solid rgba(255,255,255,.14);border-radius:10px;padding:7px 10px;background:#dc2626;color:#fff;font:700 12px/1.25 system-ui,sans-serif;cursor:pointer;text-align:left}
    .aio-landing-editor-toolbar button:hover{background:#b91c1c}.aio-landing-editor-toolbar button span{display:block;min-width:0;width:100%;overflow:hidden;color:#fee2e2;font-weight:500;text-align:left;text-overflow:ellipsis;white-space:nowrap}
    .aio-landing-editor-toolbar-content{position:relative}
    .aio-landing-editor-row{display:grid;grid-template-columns:24px minmax(0,1fr);gap:4px}.aio-landing-editor-row.is-dragging{background:rgba(255,255,255,.07);outline:2px dashed #94a3b8;outline-offset:-2px;border-radius:10px}.aio-landing-editor-row.is-dragging>*{visibility:hidden}
    .aio-landing-editor-toolbar.is-sorting{user-select:none;cursor:grabbing;overflow-anchor:none}
    .aio-landing-editor-toolbar.aio-landing-editor-ghost{position:fixed;right:auto;z-index:10000;display:grid;grid-template-columns:24px minmax(0,1fr);gap:4px;max-height:none;overflow:visible;margin:0;padding:0;border:0;border-radius:10px;background:#1e293b;backdrop-filter:none;box-shadow:0 10px 24px rgba(0,0,0,.4);pointer-events:none;will-change:transform}
    .aio-landing-editor-toolbar .aio-landing-editor-drag{display:block;min-height:38px;padding:0;border:0;background:transparent;color:#cbd5e1;font-size:22px;text-align:center;cursor:grab;touch-action:none;user-select:none}
    .aio-landing-editor-toolbar .aio-landing-editor-drag:active{cursor:grabbing}.aio-landing-editor-toolbar button:focus-visible{outline:2px solid #fff;outline-offset:1px}.aio-landing-editor-toolbar button:disabled{opacity:.6;cursor:wait}
    .aio-landing-editor-sort-status{margin:8px 4px 0;font-size:12px;color:#e2e8f0}.aio-landing-editor-sort-status:empty{display:none}
    @media(max-width:700px){.aio-landing-editor-toolbar{right:10px;top:10px;width:min(240px,calc(100vw - 20px));max-height:45vh}}
</style>

@unless($hasEditorScript ?? false)
    @include('theme-xd0302::partials.scripts')
@endunless
