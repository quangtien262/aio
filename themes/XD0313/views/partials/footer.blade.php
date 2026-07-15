<footer id="footer" class="rx13-footer">
    <div class="rx13-container rx13-footer__grid">
        <section>
            <a class="rx13-brand rx13-brand--footer" href="#top">
                @if(filled($logoUrl ?? null))
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
                @else
                    <span class="rx13-brand__mark">R</span><strong>RouteX</strong>
                @endif
            </a>
            <p>{{ $companyDescription ?? 'Tu van visa va du hoc chuyen nghiep, dong hanh cung ban tren moi hanh trinh kham pha the gioi.' }}</p>
            <div class="rx13-socials" aria-label="Mang xa hoi"><a href="#footer">f</a><a href="#footer">t</a><a href="#footer">y</a><a href="#footer">p</a></div>
        </section>
        <section>
            <h3>Danh muc visa</h3>
            <ul>
                <li><a href="#visa-noi-bat">Visa Chau Uc</a></li>
                <li><a href="#visa-noi-bat">Visa Chau My</a></li>
                <li><a href="#cac-loai-visa">Visa Chau Au</a></li>
                <li><a href="#cac-loai-visa">Visa Chau A</a></li>
            </ul>
        </section>
        <section>
            <h3>Lien ket</h3>
            <ul>
                @foreach(collect($navItems ?? [])->take(5) as $item)
                    <li><a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Trang chu' }}</a></li>
                @endforeach
            </ul>
        </section>
        <section>
            <h3>Dang ky nhan tin</h3>
            <p>Nhan thong tin visa va uu dai moi nhat.</p>
            <form class="rx13-newsletter" onsubmit="return false">
                <input type="email" placeholder="Dia chi email">
                <button type="submit">Dang ky</button>
            </form>
            <p class="rx13-footer-contact">{{ $supportAddress }}<br>{{ $hotline }}<br>{{ $supportEmail }}</p>
        </section>
    </div>
</footer>
