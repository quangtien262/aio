@php
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
@endphp

<footer class="book20-footer">
    <div class="book20-container book20-contact-strip">
        <div>
            <i class="fa-solid fa-headphones"></i>
            <span>
                Điện thoại
                <strong>{{ data_get($branding, 'support_hotline', '') }}</strong>
            </span>
        </div>
        <div>
            <i class="fa-regular fa-envelope"></i>
            <span>
                Email
                <strong>{{ data_get($branding, 'support_email', '') }}</strong>
            </span>
        </div>
        <div>
            <i class="fa-solid fa-location-dot"></i>
            <span>
                Địa chỉ
                <strong>{{ data_get($branding, 'support_location', '') }}</strong>
            </span>
        </div>
    </div>

    <div class="book20-container book20-footer-grid">
        <section>
            <h2>
                @if (filled(data_get($branding, 'logo_url')))
                    <img
                        class="book20-footer-logo"
                        src="{{ data_get($branding, 'logo_url') }}"
                        alt="{{ data_get($siteProfile ?? [], 'site_name', 'Bookle') }}"
                    >@endif
            </h2>
            <p>Không gian sách ấm cúng, gần gũi như một thư viện cá nhân dành cho mọi độc giả.</p>
            <div class="book20-social">
                <a href="#" aria-label="Facebook">f</a>
                <a href="#" aria-label="Yêu thích">♥</a>
                <a href="#" aria-label="YouTube">▶</a>
                <a href="#" aria-label="Pinterest">p</a>
            </div>
        </section>

        <section>
            <h3>Danh mục sản phẩm</h3>
            <a href="#">Văn học trong nước</a>
            <a href="#">Văn học nước ngoài</a>
            <a href="#">Sách giáo khoa</a>
            <a href="#">Sách kinh tế</a>
            <a href="#">Thiếu nhi - truyện tranh</a>
        </section>

        <section>
            <h3>Danh mục dịch vụ</h3>
            <a href="#">Dịch vụ pháp lý</a>
            <a href="#">Dịch vụ doanh nghiệp</a>
            <a href="#">Dịch vụ khách hàng</a>
            <div class="book20-payments">VISA · AMEX · PAY</div>
        </section>

        <section>
            <h3>Đăng ký nhận tin</h3>
            <p>Đăng ký nhận bản tin hàng tuần để nhận thông tin cập nhật mới nhất.</p>
            <form>
                <input type="email" placeholder="Địa chỉ email....." aria-label="Địa chỉ email">
                <button type="submit" aria-label="Đăng ký">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        </section>
    </div>
</footer>
