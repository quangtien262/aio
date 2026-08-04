<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $host = strtolower((string) request()->getHost());
            $isLocalHost = in_array($host, ['127.0.0.1', 'localhost', '::1', '[::1]'], true);
            $shouldUseHotReload = $isLocalHost && file_exists(public_path('hot'));
            $manifest = null;
            $adminEntry = null;

            if (! $shouldUseHotReload && file_exists(public_path('build/manifest.json'))) {
                $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
                $adminEntry = is_array($manifest) ? ($manifest['resources/admin/src/main.jsx'] ?? null) : null;
            }
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $adminDocumentTitle }}</title>
        @if ($shouldUseHotReload)
            @viteReactRefresh
            @vite('resources/admin/src/main.jsx')
        @elseif (is_array($adminEntry))
            @foreach (($adminEntry['css'] ?? []) as $cssFile)
                <link rel="stylesheet" href="{{ asset('build/' . ltrim($cssFile, '/')) }}">
            @endforeach
            <script type="module" src="{{ asset('build/' . ltrim((string) ($adminEntry['file'] ?? ''), '/')) }}"></script>
        @else
            @vite('resources/admin/src/main.jsx')
        @endif
    </head>
    <body>
        <div id="admin-root"></div>
    </body>
</html>
