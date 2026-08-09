@php
    $canEditLanding = true;
    $hero = [];
    $heroSlides = [];
@endphp

@include('theme-foot403::partials.inline-editor-styles')

@unless($hasButtons)
    <aside class="aio-landing-editor-toolbar" aria-label="Chỉnh sửa nhanh landing page">
        <strong>Chỉnh sửa trang</strong>
        <div>
            @foreach($landingBlocks as $block)
                <button type="button" data-xd-edit-block="{{ data_get($block, 'id') }}">
                    Sửa khối <span>{{ data_get($block, 'data.title', data_get($block, 'label', data_get($block, 'block_type'))) }}</span>
                </button>
            @endforeach
        </div>
    </aside>
@endunless

@unless($hasEditor)
    @include('theme-xd0302::partials.inline-editor')
@endunless

<style>
    .aio-landing-editor-toolbar{position:fixed;right:18px;top:18px;z-index:110;display:grid;gap:9px;width:min(290px,calc(100vw - 36px));max-height:calc(100vh - 36px);overflow:auto;padding:12px;border:1px solid rgba(255,255,255,.4);border-radius:16px;background:rgba(15,23,42,.94);color:#fff;font:600 13px/1.4 system-ui,sans-serif;box-shadow:0 18px 50px rgba(0,0,0,.35);backdrop-filter:blur(12px)}
    .aio-landing-editor-toolbar>strong{padding:3px 4px;color:#f8fafc;font-size:14px}.aio-landing-editor-toolbar>div{display:grid;gap:6px}
    .aio-landing-editor-toolbar button{display:grid;grid-template-columns:64px minmax(0,1fr);align-items:center;gap:9px;min-height:38px;border:1px solid rgba(255,255,255,.14);border-radius:10px;padding:7px 10px;background:#dc2626;color:#fff;font:700 12px/1.25 system-ui,sans-serif;cursor:pointer;text-align:left}
    .aio-landing-editor-toolbar button:hover{background:#b91c1c}.aio-landing-editor-toolbar button span{display:block;min-width:0;width:100%;overflow:hidden;color:#fee2e2;font-weight:500;text-align:left;text-overflow:ellipsis;white-space:nowrap}
    @media(max-width:700px){.aio-landing-editor-toolbar{right:10px;top:10px;width:min(240px,calc(100vw - 20px));max-height:45vh}}
</style>

@unless($hasEditorScript)
    @include('theme-xd0302::partials.scripts')
@endunless
