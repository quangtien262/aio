<url>
    <loc>{{ $entry['url'] }}</loc>
@if($entry['last_modified'])
    <lastmod>{{ $entry['last_modified'] }}</lastmod>
@endif
@foreach($entry['alternates'] as $locale => $url)
    <xhtml:link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}" />
@endforeach
</url>
