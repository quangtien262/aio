@extends('theme-ca0050::layout')
@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $company = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', '')));
    $phone = trim((string) ($branding['support_hotline'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? ''));
    $address = trim((string) ($branding['support_location'] ?? ''));
    $pageTitle = __('Liên hệ').($company ? ' | '.$company : '');
    $contactErrors = session('errors', new \Illuminate\Support\ViewErrorBag);
@endphp
@section('content')
<main class="ca50-contact-page">
<div class="ca50-contact-container">
    <nav class="ca50-contact-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home', ['locale' => app()->getLocale()]) }}">{{ __('Trang chủ') }}</a><span>/</span><span aria-current="page">{{ __('Liên hệ') }}</span></nav>
    <header class="ca50-contact-heading"><span>{{ $company }}</span><h1>{{ __('Kết nối với chúng tôi') }}</h1><p>{{ __('Bạn cần tư vấn về cá cảnh, phụ kiện hay chăm sóc bể thủy sinh? Hãy để lại lời nhắn để chúng tôi hỗ trợ bạn.') }}</p></header>
    <div class="ca50-contact-layout">
        <aside class="ca50-contact-info" aria-labelledby="ca50-contact-info-title">
            <h2 id="ca50-contact-info-title">{{ __('Thông tin liên hệ') }}</h2><p>{{ __('Liên hệ trực tiếp hoặc gửi yêu cầu qua biểu mẫu bên cạnh.') }}</p>
            @if($phone)<div class="ca50-contact-item"><i class="fa-solid fa-phone" aria-hidden="true"></i><div><h3>{{ __('Điện thoại') }}</h3><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></div></div>@endif
            @if($email)<div class="ca50-contact-item"><i class="fa-regular fa-envelope" aria-hidden="true"></i><div><h3>{{ __('Email') }}</h3><a href="mailto:{{ $email }}">{{ $email }}</a></div></div>@endif
            @if($address)<div class="ca50-contact-item"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><div><h3>{{ __('Địa chỉ') }}</h3><p>{{ $address }}</p><a class="ca50-contact-directions" href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($address) }}" target="_blank" rel="noopener noreferrer">{{ __('Xem đường đi') }} ↗</a></div></div>@endif
        </aside>
        <section class="ca50-contact-form-card" aria-labelledby="ca50-contact-form-title">
            <h2 id="ca50-contact-form-title">{{ __('Gửi lời nhắn') }}</h2><p>{{ __('Các trường có dấu * là bắt buộc.') }}</p>
            @if(session('contact_status'))<div class="ca50-contact-notice" role="status">{{ session('contact_status') }}</div>@endif
            @if($contactErrors->any())<div class="ca50-contact-error" role="alert"><strong>{{ __('Vui lòng kiểm tra lại thông tin:') }}</strong><ul>@foreach($contactErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route('site.contact.submit', ['locale' => app()->getLocale()]) }}">
                @csrf
                <input type="hidden" name="source" value="contact">
                <div class="ca50-contact-fields">
                    @foreach(['name' => [__('Họ và tên'), 'text', 'name', 120, true], 'phone' => [__('Số điện thoại'), 'tel', 'tel', 30, false], 'email' => [__('Email'), 'email', 'email', 150, true], 'subject' => [__('Chủ đề'), 'text', 'off', 150, false]] as $field => [$label, $type, $autocomplete, $max, $required])
                    <div @class(['ca50-contact-field-wide' => in_array($field, ['email', 'subject'])])><label for="ca50-contact-{{ $field }}">{{ $label }}{{ $required ? ' *' : '' }}</label><input id="ca50-contact-{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" maxlength="{{ $max }}" value="{{ old($field) }}" @required($required) @if($contactErrors->has($field)) aria-invalid="true" @endif></div>
                    @endforeach
                    <div class="ca50-contact-field-wide"><label for="ca50-contact-message">{{ __('Nội dung') }} *</label><textarea id="ca50-contact-message" name="message" rows="5" minlength="10" maxlength="5000" required aria-describedby="ca50-contact-hint" @if($contactErrors->has('message')) aria-invalid="true" @endif>{{ old('message') }}</textarea><small id="ca50-contact-hint">{{ __('Vui lòng nhập ít nhất 10 ký tự để mô tả nhu cầu của bạn.') }}</small></div>
                </div>
                <button type="submit">{{ __('Gửi liên hệ') }} <i class="fa-regular fa-paper-plane" aria-hidden="true"></i></button>
            </form>
        </section>
    </div>
</div>
</main>
@endsection
