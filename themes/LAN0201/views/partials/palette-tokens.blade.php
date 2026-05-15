@php
    $thPrimary = data_get($branding ?? [], 'primary_color', '#d67a2c');
    $thPrimaryDeep = data_get($branding ?? [], 'primary_color_deep', '#af5f1f');
    $thAccent = data_get($branding ?? [], 'accent_color', '#d98d4a');
    $thAccentSoft = data_get($branding ?? [], 'accent_soft_color', '#efaa4c');
    $thBackground = data_get($branding ?? [], 'background_color', '#faf6f1');
    $thSurface = data_get($branding ?? [], 'surface_color', '#ffffff');
    $thSurfaceTint = data_get($branding ?? [], 'surface_tint_color', '#fff4e8');
@endphp
:root {
    --th-red: {{ $thPrimary }};
    --th-red-deep: {{ $thPrimaryDeep }};
    --th-red-dark: {{ $thPrimaryDeep }};
    --th-green: {{ $thPrimary }};
    --th-green-dark: {{ $thPrimaryDeep }};
    --th-ink: #222222;
    --th-muted: #6d6d6d;
    --th-line: #e6e6e6;
    --th-bg: {{ $thBackground }};
    --th-surface: {{ $thSurface }};
    --th-soft: {{ $thSurfaceTint }};
    --th-warm: {{ $thSurfaceTint }};
    --th-pink: {{ $thAccent }};
    --th-lime: {{ $thPrimaryDeep }};
    --th-orange: {{ $thAccentSoft }};
    --th-shadow: 0 18px 40px rgba(19, 21, 33, 0.08);
}
