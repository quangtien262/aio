<footer id="footer" class="xd4-footer">
    <div class="xd4-container xd4-footer__grid">
        <div><a class="xd4-brand" href="#top">@if(filled($logoUrl ?? null))<img src="{{ $logoUrl }}" alt="{{ $companyName }}">@else<span class="xd4-brand__mark">L</span><strong>{{ $companyName }}</strong>@endif</a><p>{{ $companyDescription ?? 'Giáº£i phÃ¡p váº­n táº£i vÃ  háº­u cáº§n linh hoáº¡t cho doanh nghiá»‡p.' }}</p><p>{{ $supportAddress ?? '' }}</p><p><a href="tel:{{ $phoneHref ?? '' }}">{{ $hotline ?? '' }}</a></p><p><a href="mailto:{{ $supportEmail ?? '' }}">{{ $supportEmail ?? '' }}</a></p></div>
        <div><h3>Dá»‹ch vá»¥</h3><ul>@foreach (($navItems ?? []) as $item)<li><a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'LiÃªn káº¿t' }}</a></li>@endforeach</ul></div>
        <div><h3>LiÃªn káº¿t há»¯u Ã­ch</h3><ul><li><a href="#gioi-thieu">Giá»›i thiá»‡u</a></li><li><a href="#dich-vu">Dá»‹ch vá»¥</a></li><li><a href="#thu-vien">ThÆ° viá»‡n</a></li><li><a href="#footer">LiÃªn há»‡</a></li></ul></div>
        <div><h3>ÄÄƒng kÃ½ báº£n tin</h3><p>Nháº­n thÃ´ng tin má»›i nháº¥t vá» dá»‹ch vá»¥ vÃ  giáº£i phÃ¡p váº­n táº£i cá»§a chÃºng tÃ´i.</p><form class="xd4-newsletter"><input type="email" placeholder="Äá»‹a chá»‰ email" aria-label="Äá»‹a chá»‰ email"><button type="submit" aria-label="ÄÄƒng kÃ½">â†’</button></form></div>
    </div>
    <div class="xd4-footer__bottom">Â© {{ now()->year }} {{ $companyName }}. All rights reserved.</div>
</footer>
