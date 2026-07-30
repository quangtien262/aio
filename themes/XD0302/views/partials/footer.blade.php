@php
    $footerHeading = 'Bản đồ chỉ đường';
    $footerCopyright = '© Bản quyền nội dung thuộc về '.($companyName ?? 'Soler Panel');
    $mapUrl = 'https://www.openstreetmap.org/export/embed.html?bbox=106.66%2C10.72%2C106.72%2C10.77&amp;layer=mapnik';
@endphp
<footer class="xd2-footer">
    <div class="xd2-container xd2-footer__grid">
        <div>
            <a class="xd2-logo xd2-logo--footer" href="{{ route('site.home') }}"><span class="xd2-logo__mark">SP</span><span>{{ $companyName ?? 'Soler Panel' }}<small>Energy Company</small></span></a>
            <h3>Địa chỉ</h3><p>{{ $supportAddress ?? '' }}</p>
            <h3>Nhận email</h3><p>{{ $supportEmail ?? '' }}</p>
            <h3>Số điện thoại</h3><p>{{ $hotline ?? '' }}</p>
        </div>
        <div><h3>Menu dịch vụ</h3><nav class="xd2-footer__links">@foreach (collect($footerNavItems ?? [])->take(6) as $item)<a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Menu' }}</a>@endforeach</nav></div>
        <div><h3>{{ $footerHeading }}</h3><div class="xd2-map"><iframe title="{{ $footerHeading }}" loading="lazy" src="{{ $mapUrl }}"></iframe></div></div>
    </div>
    <p class="xd2-footer__copyright">{{ $footerCopyright }}</p>
</footer>
