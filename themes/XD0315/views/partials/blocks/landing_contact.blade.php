@php
    $contactFormTitle = trim((string) data_get($content, 'form_title', ''));
    $contactNoteTitle = trim((string) data_get($content, 'note_title', ''));
    $contactNoteText = trim((string) data_get($content, 'note_text', ''));
    $contactSubmitLabel = trim((string) ($data['button_label'] ?? ''));

    $contactFormTitle = $contactFormTitle !== ''
        ? $contactFormTitle
        : (app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0315', app()->getLocale(), 'legacy_inline.87c2af088eba47e9', 'Gửi yêu cầu liên hệ'));
    $contactNoteTitle = $contactNoteTitle !== ''
        ? $contactNoteTitle
        : (app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0315', app()->getLocale(), 'legacy_inline.e8d82960e1ea853a', 'Chia sẻ nhu cầu, chúng tôi tư vấn đúng giải pháp.'));
    $contactNoteText = $contactNoteText !== ''
        ? $contactNoteText
        : (app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0315', app()->getLocale(), 'legacy_inline.90c7204030ac6333', 'Hãy gửi thêm địa điểm, diện tích, tiến độ mong muốn hoặc yêu cầu kỹ thuật để đội ngũ chuẩn bị phương án phù hợp ngay từ lần phản hồi đầu tiên.'));
    $contactSubmitLabel = $contactSubmitLabel !== ''
        ? $contactSubmitLabel
        : (app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0315', app()->getLocale(), 'legacy_inline.fc4ed14bcef77494', 'Gửi liên hệ'));
@endphp

<section id="{{ $anchor }}" class="xd-section xd-contact-band xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="xd-container">
        <div class="xd-contact-page">
            <aside class="xd-contact-panel">
                @if (filled($data['subtitle'] ?? null))
                    <span class="xd-kicker">{{ $data['subtitle'] }}</span>
                @endif
                <h2>{!! nl2br(e($data['title'] ?? $companyName)) !!}</h2>
                @if (filled($data['description'] ?? null))
                    <p>{!! nl2br(e($data['description'])) !!}</p>
                @endif
                <ul class="xd-contact-methods">
                    <li class="xd-contact-method">
                        <span class="xd-contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.35 1.9.66 2.81a2 2 0 0 1-.45 2.11L8.05 9.91a16 16 0 0 0 6.04 6.04l1.27-1.27a2 2 0 0 1 2.11-.45c.91.31 1.85.53 2.81.66A2 2 0 0 1 22 16.92z"/>
                            </svg>
                        </span>
                        <div>
                            <small>Hotline</small>
                            <a href="tel:{{ $phoneHref }}">{{ $hotline }}</a>
                        </div>
                    </li>
                    <li class="xd-contact-method">
                        <span class="xd-contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="5" width="18" height="14" rx="2"/>
                                <path d="m3 7 9 6 9-6"/>
                            </svg>
                        </span>
                        <div>
                            <small>Email</small>
                            <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                        </div>
                    </li>
                    <li class="xd-contact-method">
                        <span class="xd-contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 21s7-5.3 7-12a7 7 0 1 0-14 0c0 6.7 7 12 7 12z"/>
                                <circle cx="12" cy="9" r="2.5"/>
                            </svg>
                        </span>
                        <div>
                            <small>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0315', app()->getLocale(), 'legacy_inline.1347baf91daa2f61', 'Địa chỉ') }}</small>
                            <span>{{ $supportAddress }}</span>
                        </div>
                    </li>
                </ul>
                <div class="xd-contact-note">
                    <strong>{{ $contactNoteTitle }}</strong>
                    <span>{{ $contactNoteText }}</span>
                </div>
            </aside>

            <article class="xd-contact-form-card">
                <h2>{{ $contactFormTitle }}</h2>
                @if (session('contact_status'))
                    <div class="xd-contact-alert">{{ session('contact_status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="xd-contact-errors">
                        {{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0315', app()->getLocale(), 'legacy_inline.296a08c028483e0d', 'Vui lòng kiểm tra lại thông tin.') }}
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form class="xd-contact-form" method="POST" action="{{ route('site.contact.submit') }}">
                    @csrf
                    <input type="hidden" name="source" value="landing_contact">
                    <input type="hidden" name="subject" value="{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0315', app()->getLocale(), 'legacy_inline.86f0eaa8c37c43a7', 'Yêu cầu liên hệ từ landing page') }}">
                    <label class="xd-contact-field">
                        <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0315', app()->getLocale(), 'legacy_inline.0aa2131688fe9560', 'Họ tên') }}</span>
                        <input name="name" value="{{ old('name') }}" required autocomplete="name">
                    </label>
                    <label class="xd-contact-field">
                        <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0315', app()->getLocale(), 'legacy_inline.051dc5b8bfcbebd3', 'Số điện thoại') }}</span>
                        <input name="phone" value="{{ old('phone') }}" autocomplete="tel">
                    </label>
                    <label class="xd-contact-field">
                        <span>Email</span>
                        <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                    </label>
                    <label class="xd-contact-field">
                        <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0315', app()->getLocale(), 'legacy_inline.4da784274f11f7e5', 'Nội dung') }}</span>
                        <textarea name="message" required>{{ old('message') }}</textarea>
                    </label>
                    <button class="xd-contact-submit" type="submit">{{ $contactSubmitLabel }}</button>
                </form>
            </article>
        </div>
    </div>
</section>


