@php
    $storefrontLocales = collect(\App\Support\FrontendLocalization::localeOptions())
        ->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false)
            && (bool) ($locale['is_published'] ?? true)
            && filled($locale['code'] ?? null))
        ->values();
    $storefrontCurrentLocale = \App\Support\FrontendLocalization::resolveLocale(app()->getLocale());
    $storefrontCurrentOption = $storefrontLocales->firstWhere('code', $storefrontCurrentLocale);
    $storefrontLanguageUrls = \App\Support\FrontendRouteUrl::localeSwitchUrls(
        $storefrontLocales->pluck('code')->all(),
    );
    $storefrontLanguageAlternates = collect($hreflangUrls ?? [])
        ->filter(fn (mixed $url, mixed $locale): bool => (
            is_string($locale)
            && $locale !== 'x-default'
            && is_string($url)
            && filled($url)
        ))
        ->all();
    $storefrontLocaleLabel = static fn (array $locale): string => trim((string) (
        ($locale['native_name'] ?? null)
        ?: ($locale['name'] ?? null)
        ?: strtoupper((string) ($locale['code'] ?? ''))
    ));
@endphp

@if($storefrontLocales->count() > 1)
    @once
        <style>
            .sf-language-switcher{position:fixed;z-index:1200;inset-block-start:max(10px,env(safe-area-inset-top));inset-inline-end:max(10px,env(safe-area-inset-right));font:600 13px/1.2 "Segoe UI",Roboto,Arial,sans-serif;color:#172033;text-align:start}
            .sf-language-switcher *{box-sizing:border-box}
            .sf-language-switcher details{position:relative}
            .sf-language-switcher summary{display:flex;align-items:center;gap:7px;min-height:38px;padding:7px 10px;border:1px solid rgba(23,32,51,.16);border-radius:999px;background:rgba(255,255,255,.94);color:#172033;box-shadow:0 8px 24px rgba(15,23,42,.16);backdrop-filter:blur(14px);cursor:pointer;list-style:none;user-select:none}
            .sf-language-switcher summary::-webkit-details-marker{display:none}
            .sf-language-switcher summary:focus-visible{outline:3px solid rgba(37,99,235,.28);outline-offset:2px}
            .sf-language-switcher__globe{width:16px;height:16px;flex:0 0 16px}
            .sf-language-switcher__code{min-width:20px;font-size:12px;font-weight:800;letter-spacing:.04em;text-align:center}
            .sf-language-switcher__chevron{width:10px;height:10px;transition:transform .18s ease}
            .sf-language-switcher details[open] .sf-language-switcher__chevron{transform:rotate(180deg)}
            .sf-language-switcher__menu{position:absolute;inset-block-start:calc(100% + 8px);inset-inline-end:0;display:grid;min-width:190px;max-height:min(360px,70vh);margin:0;padding:6px;overflow:auto;border:1px solid rgba(23,32,51,.12);border-radius:14px;background:#fff;box-shadow:0 18px 50px rgba(15,23,42,.2)}
            .sf-language-switcher__menu a{display:grid;grid-template-columns:42px minmax(0,1fr) 16px;align-items:center;gap:8px;min-height:42px;padding:8px 10px;border-radius:10px;color:#334155!important;text-decoration:none!important;white-space:nowrap}
            .sf-language-switcher__menu a:hover,.sf-language-switcher__menu a:focus-visible{background:#f1f5f9;color:#0f172a!important;outline:0}
            .sf-language-switcher__menu a.is-active{background:#e8f0ff;color:#1746a2!important}
            .sf-language-switcher__menu-code{font-size:11px;font-weight:850;letter-spacing:.05em}
            .sf-language-switcher__menu-label{min-width:0;overflow:hidden;text-overflow:ellipsis;font-weight:650}
            .sf-language-switcher__check{width:15px;height:15px;opacity:0}
            .sf-language-switcher__menu a.is-active .sf-language-switcher__check{opacity:1}
            @media(max-width:640px){.sf-language-switcher{inset-block-start:max(66px,calc(env(safe-area-inset-top) + 8px));inset-inline-end:max(8px,env(safe-area-inset-right))}.sf-language-switcher summary{min-height:36px;padding:6px 9px}.sf-language-switcher__menu{max-width:calc(100vw - 16px)}}
            @media(prefers-reduced-motion:reduce){.sf-language-switcher__chevron{transition:none}}
        </style>
    @endonce

    <nav
        class="sf-language-switcher"
        data-storefront-language-switcher
        data-current-locale="{{ $storefrontCurrentLocale }}"
        aria-label="Language"
    >
        <details>
            <summary aria-label="Language: {{ $storefrontLocaleLabel($storefrontCurrentOption ?? ['code' => $storefrontCurrentLocale]) }}">
                <svg class="sf-language-switcher__globe" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path>
                </svg>
                <span class="sf-language-switcher__code">{{ strtoupper(explode('-', $storefrontCurrentLocale)[0]) }}</span>
                <svg class="sf-language-switcher__chevron" aria-hidden="true" viewBox="0 0 12 8" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="m1 1 5 5 5-5"></path>
                </svg>
            </summary>
            <div class="sf-language-switcher__menu">
                @foreach($storefrontLocales as $storefrontLocale)
                    @php
                        $storefrontLocaleCode = (string) $storefrontLocale['code'];
                        $storefrontLocaleIsCurrent = $storefrontLocaleCode === $storefrontCurrentLocale;
                    @endphp
                    <a
                        class="{{ $storefrontLocaleIsCurrent ? 'is-active' : '' }}"
                        href="{{ $storefrontLanguageAlternates[$storefrontLocaleCode] ?? $storefrontLanguageUrls[$storefrontLocaleCode] ?? \App\Support\FrontendRouteUrl::home($storefrontLocaleCode) }}"
                        hreflang="{{ $storefrontLocaleCode }}"
                        lang="{{ $storefrontLocaleCode }}"
                        rel="alternate"
                        data-locale-code="{{ $storefrontLocaleCode }}"
                        @if($storefrontLocaleIsCurrent) aria-current="true" @endif
                    >
                        <span class="sf-language-switcher__menu-code">{{ strtoupper(explode('-', $storefrontLocaleCode)[0]) }}</span>
                        <span class="sf-language-switcher__menu-label">{{ $storefrontLocaleLabel($storefrontLocale) }}</span>
                        <svg class="sf-language-switcher__check" aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m2.5 8.5 3 3 8-8"></path>
                        </svg>
                    </a>
                @endforeach
            </div>
        </details>
    </nav>
@endif
