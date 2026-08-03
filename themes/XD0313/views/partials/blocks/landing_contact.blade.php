@php
    $bgImage = $media['background'] ?? $content['background'] ?? 'https://images.unsplash.com/photo-1494412685616-a5d310fbb07d?auto=format&fit=crop&w=1800&q=85';
@endphp

<section id="{{ $anchor }}" class="rx13-section rx13-contact xd-landing-block" style="--rx13-bg: url('{{ $bgImage }}')" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="rx13-container rx13-contact__grid">
        <div>
            <h2>{{ $data['title'] ?? 'Yêu cầu một cuộc gọi lại' }}</h2>
            @if (filled($data['description'] ?? null))
                <p>{{ $data['description'] }}</p>
            @endif
            <p>Hoặc liên hệ trực tiếp với chúng tôi</p>
        </div>
        <form class="rx13-form" method="POST" action="{{ route('site.contact.submit') }}">
            @csrf
            <input type="hidden" name="source" value="XD0313-callback">
            <input name="name" required placeholder="*Họ và tên" value="{{ old('name') }}">
            <input type="email" name="email" required placeholder="*Email" value="{{ old('email') }}">
            <input name="phone" required placeholder="*Số điện thoại" value="{{ old('phone') }}">
            <input name="address" placeholder="Địa chỉ" value="{{ old('address') }}">
            <textarea name="message" class="is-wide" required placeholder="*Nội dung">{{ old('message') }}</textarea>
            <input name="captcha" class="is-wide" placeholder="*Ma bao mat">
            <button type="submit">{{ $data['button_label'] ?? 'Gửi tin nhắn' }}</button>
        </form>
    </div>
</section>
