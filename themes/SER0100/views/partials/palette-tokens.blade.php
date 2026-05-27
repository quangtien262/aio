@php
    $primary = data_get($branding ?? [], 'primary_color', '#c2410c');
    $primaryDeep = data_get($branding ?? [], 'primary_color_deep', '#ea580c');
    $accent = data_get($branding ?? [], 'accent_color', '#1f6f78');
    $accentSoft = data_get($branding ?? [], 'accent_soft_color', '#f59e0b');
    $background = data_get($branding ?? [], 'background_color', '#f7fbfd');
    $surface = data_get($branding ?? [], 'surface_color', '#ffffff');
    $surfaceTint = data_get($branding ?? [], 'surface_tint_color', '#eef5f7');
@endphp
:root {
    --ser-navy: #102a43;
    --ser-night: #081a2a;
    --ser-petrol: {{ $accent }};
    --ser-ink: #243b53;
    --ser-orange: {{ $primary }};
    --ser-orange-deep: {{ $primaryDeep }};
    --ser-amber: {{ $accentSoft }};
    --ser-sand: {{ $background }};
    --ser-mist: {{ $surfaceTint }};
    --ser-line: #d9e2ec;
    --ser-white: {{ $surface }};
    --ser-muted: #627d98;
    --ser-shadow: 0 24px 60px rgba(15, 42, 67, 0.12);
    --n: var(--ser-navy);
    --night: var(--ser-night);
    --p: var(--ser-petrol);
    --o: var(--ser-orange);
    --o-deep: var(--ser-orange-deep);
    --a: var(--ser-amber);
    --l: var(--ser-line);
    --m: var(--ser-muted);
    --navy: var(--ser-navy);
    --orange: var(--ser-orange);
    --line: var(--ser-line);
    --muted: var(--ser-muted);
    --bg: var(--ser-sand);
    --shadow: var(--ser-shadow);
}
