@php
    $thPrimary = data_get($branding ?? [], 'primary_color', '#ef2b2d');
    $thPrimaryDeep = data_get($branding ?? [], 'primary_color_deep', '#d91c20');
    $thAccent = data_get($branding ?? [], 'accent_color', '#79c400');
    $thAccentSoft = data_get($branding ?? [], 'accent_soft_color', '#86c440');
    $thBackground = data_get($branding ?? [], 'background_color', '#f6f6f8');
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
    --th-ink: #222222;
    --th-muted: #6d6d6d;
    --th-line: #e6e6e6;
    --th-bg: {{ $thBackground }};
    --th-surface: {{ $thSurface }};
    --th-soft: {{ $thSurfaceTint }};
    --th-warm: {{ $thSurfaceTint }};
    --th-shadow: 0 18px 40px rgba(19, 21, 33, 0.08);
}
