{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($snapshot['files'] as $name => $entries)
    <sitemap><loc>{{ $snapshot['base_url'].'/sitemaps/'.$name.'.xml' }}</loc></sitemap>
@endforeach
</sitemapindex>
