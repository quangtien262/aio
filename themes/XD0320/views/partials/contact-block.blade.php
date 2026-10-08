@php
    $contactBranding = (array) data_get($siteProfile ?? [], 'branding', []);
    $contactTitle = data_get($contact, 'data.title') ?: __('theme_contact_page.heading');
    $contactIntro = data_get($contact, 'data.description') ?: __('theme_contact_page.intro');
@endphp
@include('theme-xd0320::partials.contact-styles')
@if(!empty($contact))
<section id="lien-he" class="xd20-contact-section" aria-labelledby="xd20-contact-title" @if(!empty($contact['id'])) data-landing-block-id="{{ $contact['id'] }}" data-block-type="landing_contact" @endif>
    <div class="xd20-container xd20-contact-shell">
        <div class="xd20-contact-copy">
            <span class="xd20-contact-kicker">{{ data_get($contact, 'data.subtitle') ?: __('theme_contact_page.title') }}</span>
            <h2 id="xd20-contact-title">{{ $contactTitle }}</h2>
            <p>{{ $contactIntro }}</p>
            <dl class="xd20-contact-details">
                @foreach(['support_hotline' => ['phone', 'fa-phone'], 'support_email' => ['email', 'fa-envelope'], 'support_location' => ['address', 'fa-location-dot']] as $key => [$label, $icon])
                    @if(filled($contactBranding[$key] ?? null))
                        <div><i class="fa-solid {{ $icon }}" aria-hidden="true"></i><div><dt>{{ __('theme_contact_page.'.$label) }}</dt><dd>
                            @if($key === 'support_hotline')<a href="tel:{{ preg_replace('/[^0-9+]/', '', $contactBranding[$key]) }}">{{ $contactBranding[$key] }}</a>
                            @elseif($key === 'support_email')<a href="mailto:{{ $contactBranding[$key] }}">{{ $contactBranding[$key] }}</a>
                            @else{{ $contactBranding[$key] }}@endif
                        </dd></div></div>
                    @endif
                @endforeach
            </dl>
        </div>
        <div class="xd20-contact-card">
            <h3>{{ data_get($contact, 'data.content.form_title') ?: __('theme_contact_page.message_heading') }}</h3>
            @if(session('contact_status'))<p class="xd20-contact-success" role="status">{{ session('contact_status') }}</p>@endif
            @include('theme-xd0320::partials.contact-form', ['formId' => 'xd20-home-contact'])
        </div>
    </div>
</section>
@endif
<dialog id="xd20-consultation" class="xd20-consultation" data-xd20-consultation aria-labelledby="xd20-consultation-title">
    <div class="xd20-consultation-top"><span class="xd20-contact-kicker">{{ data_get($contact, 'data.subtitle') ?: __('theme_contact_page.title') }}</span><button type="button" data-xd20-consultation-close aria-label="{{ app()->getLocale() === 'en' ? 'Close' : 'Đóng' }}">×</button></div>
    <h2 id="xd20-consultation-title">{{ data_get($contact, 'data.content.modal_title') ?: __('theme_contact_page.message_heading') }}</h2>
    <p class="xd20-consultation-intro">{{ $contactIntro }}</p>
    @include('theme-xd0320::partials.contact-form', ['formId' => 'xd20-modal-contact'])
</dialog>
@push('scripts')
    <script defer src="{{ asset('js/xd0320-contact.js') }}"></script>
@endpush
