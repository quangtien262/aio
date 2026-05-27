@php
    $primary = data_get($branding ?? [], 'primary_color', '#0f766e');
    $primaryDeep = data_get($branding ?? [], 'primary_color_deep', '#0f5d56');
    $accent = data_get($branding ?? [], 'accent_color', '#f0b429');
    $accentSoft = data_get($branding ?? [], 'accent_soft_color', '#f6d365');
    $background = data_get($branding ?? [], 'background_color', '#f6faf7');
    $surface = data_get($branding ?? [], 'surface_color', '#ffffff');
    $surfaceTint = data_get($branding ?? [], 'surface_tint_color', '#eef6f4');
@endphp
:root {
    --ser-navy: #102a43;
    --ser-night: #0b1b26;
    --ser-primary: {{ $primary }};
    --ser-primary-deep: {{ $primaryDeep }};
    --ser-accent: {{ $accent }};
    --ser-accent-soft: {{ $accentSoft }};
    --ser-ink: #18324a;
    --ser-bg: {{ $background }};
    --ser-surface: {{ $surface }};
    --ser-mist: {{ $surfaceTint }};
    --ser-line: #d6e2de;
    --ser-muted: #5d7288;
    --ser-shadow: 0 28px 70px rgba(10, 30, 47, 0.12);
    --n: var(--ser-navy);
    --night: var(--ser-night);
    --p: var(--ser-primary);
    --p-deep: var(--ser-primary-deep);
    --a: var(--ser-accent);
    --a-soft: var(--ser-accent-soft);
    --l: var(--ser-line);
    --m: var(--ser-muted);
    --navy: var(--ser-navy);
    --teal: var(--ser-primary);
    --orange: var(--ser-accent);
    --line: var(--ser-line);
    --muted: var(--ser-muted);
    --bg: var(--ser-bg);
    --shadow: var(--ser-shadow);
}
