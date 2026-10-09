@once
<style>
.xd5-contact{background-color:#151618;background-image:linear-gradient(90deg,rgba(12,19,25,.9),rgba(12,19,25,.64)),url("{{ asset('theme-demo/xd-shared/business-2.jpg') }}");background-size:cover;background-position:center;background-repeat:no-repeat}
.xd5-contact .xd5-contact-card{box-shadow:0 24px 64px #0003}
@media(max-width:900px){.xd5-contact{background-image:linear-gradient(rgba(12,19,25,.85),rgba(12,19,25,.75)),url("{{ asset('theme-demo/xd-shared/business-2.jpg') }}")}}
</style>
@endonce
<section id="{{ $anchor }}" class="xd5-section xd5-contact"><div class="xd5-container xd5-contact-grid"><div><p class="xd5-eyebrow">{{ $data['subtitle']??'' }}</p><h2 class="xd5-title">{{ $data['title']??'' }}</h2><p>{{ $data['description']??'' }}</p><div class="xd5-contact-info"><div><b>Đặt câu hỏi</b>{{ $hotline }}</div><div><b>Gửi email</b>{{ $supportEmail }}</div><div><b>Địa chỉ</b>{{ $supportAddress }}</div></div></div><form class="xd5-contact-card"><h2>Liên hệ chúng tôi</h2><input placeholder="Họ và tên" required><input placeholder="Số điện thoại" required><input type="email" placeholder="Email" required><input placeholder="Địa chỉ"><textarea rows="6" placeholder="Nội dung"></textarea><button class="xd5-btn" type="submit">{{ $data['button_label']??'Gửi ngay' }}</button></form></div></section>
