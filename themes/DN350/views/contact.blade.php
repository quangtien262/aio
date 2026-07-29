@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $company = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Chúng tôi')));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? ''));
    $address = trim((string) ($branding['support_location'] ?? ''));
    $workingHours = trim((string) ($branding['working_hours'] ?? 'Thứ Hai - Thứ Bảy, 08:00 - 17:30'));
    $title = trim((string) ($entry->title ?? '')) ?: 'Liên hệ với chúng tôi';
    $intro = trim((string) ($entry->excerpt ?? ''))
        ?: trim((string) ($branding['company_description'] ?? 'Đội ngũ tư vấn luôn sẵn sàng lắng nghe và đồng hành cùng nhu cầu của bạn.'));
    $mapUrl = $address !== '' ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address) : '';
@endphp
@extends('theme-dn350::layout')
@section('title', $title.' | '.$company)
@section('content')
<main class="dn-contact-page">
    <section class="dn-contact-page-hero">
        <div class="dn-container dn-contact-page-hero__inner">
            <div>
                <p class="dn-eyebrow">Kết nối cùng {{ $company }}</p>
                <h1>{{ $title }}</h1>
                <p>{{ $intro }}</p>
            </div>
            <div class="dn-contact-page-hero__mark" aria-hidden="true"><i class="fa-regular fa-message"></i></div>
        </div>
    </section>

    <section class="dn-section dn-contact-page-body">
        <div class="dn-container dn-contact-page-grid">
            <aside class="dn-contact-details" data-dn-reveal="left">
                <p class="dn-eyebrow">Thông tin liên hệ</p>
                <h2>Hãy bắt đầu bằng một cuộc trao đổi</h2>
                <p class="dn-contact-details__lead">Chúng tôi tiếp nhận yêu cầu tư vấn, khảo sát, báo giá và hỗ trợ kỹ thuật qua các kênh dưới đây.</p>

                <div class="dn-contact-detail-list">
                    @if($address !== '')
                        <a class="dn-contact-detail" href="{{ $mapUrl }}" target="_blank" rel="noopener">
                            <i class="fa-solid fa-location-dot"></i><span><small>Địa chỉ</small><strong>{{ $address }}</strong></span><b class="fa-solid fa-arrow-up-right-from-square"></b>
                        </a>
                    @endif
                    @if($hotline !== '')
                        <a class="dn-contact-detail" href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}">
                            <i class="fa-solid fa-phone"></i><span><small>Hotline tư vấn</small><strong>{{ $hotline }}</strong></span><b class="fa-solid fa-arrow-right-long"></b>
                        </a>
                    @endif
                    @if($email !== '')
                        <a class="dn-contact-detail" href="mailto:{{ $email }}">
                            <i class="fa-solid fa-envelope"></i><span><small>Email</small><strong>{{ $email }}</strong></span><b class="fa-solid fa-arrow-right-long"></b>
                        </a>
                    @endif
                    <div class="dn-contact-detail">
                        <i class="fa-regular fa-clock"></i><span><small>Thời gian làm việc</small><strong>{{ $workingHours }}</strong></span>
                    </div>
                </div>

            </aside>

            <div class="dn-contact-request" data-dn-reveal="right">
                <header>
                    <span>Gửi yêu cầu</span>
                    <h2>Chúng tôi có thể hỗ trợ gì cho bạn?</h2>
                    <p>Điền thông tin bên dưới, chuyên viên sẽ liên hệ trong thời gian sớm nhất.</p>
                </header>

                @if(session('contact_status'))
                    <div class="dn-contact-alert is-success"><i class="fa-solid fa-circle-check"></i>{{ session('contact_status') }}</div>
                @endif
                @if($errors->any())
                    <div class="dn-contact-alert is-error"><i class="fa-solid fa-circle-exclamation"></i>Vui lòng kiểm tra lại các trường thông tin.</div>
                @endif

                <form class="dn-contact-page-form" method="POST" action="{{ route('site.contact.submit') }}">
                    @csrf
                    <input type="hidden" name="source" value="contact">
                    <label><span>Họ và tên *</span><input name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name" placeholder="Nguyễn Văn A">@error('name')<small>{{ $message }}</small>@enderror</label>
                    <label><span>Số điện thoại</span><input name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" placeholder="0901 234 567">@error('phone')<small>{{ $message }}</small>@enderror</label>
                    <label><span>Email *</span><input type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email" placeholder="email@domain.com">@error('email')<small>{{ $message }}</small>@enderror</label>
                    <label><span>Nhu cầu tư vấn</span><select name="subject"><option value="Tư vấn giải pháp phù hợp">Tư vấn giải pháp phù hợp</option><option value="Khảo sát và báo giá công trình">Khảo sát và báo giá công trình</option><option value="Tư vấn sản phẩm và vật liệu">Tư vấn sản phẩm và vật liệu</option><option value="Bảo trì và hỗ trợ kỹ thuật">Bảo trì và hỗ trợ kỹ thuật</option></select></label>
                    <label class="is-wide"><span>Nội dung cần hỗ trợ *</span><textarea name="message" required minlength="10" maxlength="5000" placeholder="Mô tả nhu cầu, loại công trình hoặc thời gian muốn được liên hệ...">{{ old('message') }}</textarea>@error('message')<small>{{ $message }}</small>@enderror</label>
                    <p class="dn-contact-privacy"><i class="fa-solid fa-shield-halved"></i>Thông tin của bạn chỉ được sử dụng cho mục đích tư vấn và hỗ trợ.</p>
                    <button class="dn-btn" type="submit">Gửi yêu cầu tư vấn <i class="fa-solid fa-arrow-right-long"></i></button>
                </form>
            </div>
        </div>
    </section>
</main>
@endsection
