@php
    $thPrimary = data_get($branding ?? [], 'primary_color', '#b20f3a');
    $thPrimaryDeep = data_get($branding ?? [], 'primary_color_deep', '#7f0d2d');
    $thAccent = data_get($branding ?? [], 'accent_color', '#0f8a8a');
    $thAccentSoft = data_get($branding ?? [], 'accent_soft_color', '#f2c94c');
    $thBackground = data_get($branding ?? [], 'background_color', '#f7f5f2');
    $thSurface = data_get($branding ?? [], 'surface_color', '#ffffff');
    $thSurfaceTint = data_get($branding ?? [], 'surface_tint_color', '#fff7f5');
@endphp
:root {
    --th-red: {{ $thPrimary }};
    --th-red-deep: {{ $thPrimaryDeep }};
    --th-red-dark: {{ $thPrimaryDeep }};
    --th-green: {{ $thAccent }};
    --th-green-dark: {{ $thPrimaryDeep }};
    --th-pink: {{ $thAccent }};
    --th-lime: {{ $thAccentSoft }};
    --th-orange: {{ $thAccentSoft }};
    --th-ink: #1f1a1d;
    --th-muted: #756a70;
    --th-line: #e7dfdc;
    --th-bg: {{ $thBackground }};
    --th-surface: {{ $thSurface }};
    --th-soft: {{ $thSurfaceTint }};
    --th-warm: {{ $thSurfaceTint }};
    --th-shadow: 0 18px 40px rgba(19, 21, 33, 0.08);
}
