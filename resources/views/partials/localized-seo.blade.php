@if (filled($canonicalUrl ?? null))
    <link rel="canonical" href="{{ $canonicalUrl }}">
@endif
@foreach (($hreflangUrls ?? []) as $language => $href)
    <link rel="alternate" hreflang="{{ $language }}" href="{{ $href }}">
@endforeach
