@php($contactFormId = 'xd4-contact-'.($block['id'] ?? 'home'))
<section id="{{ $anchor }}" class="xd4-section xd4-contact" data-block-type="landing_contact">
    <div class="xd4-container xd4-contact__grid">
        <div class="xd4-contact__copy">
            <p class="xd4-eyebrow">{{ $data['subtitle'] ?? __('theme_contact_page.title') }}</p>
            <h2>{{ $data['title'] ?? __('theme_contact_page.title') }}</h2>
            @if(filled($data['description'] ?? null))<p>{{ $data['description'] }}</p>@endif
            <dl class="xd4-contact__info">
                @if(filled($hotline))<div><dt>{{ __('theme_contact_page.phone_number') }}</dt><dd><a href="tel:{{ $phoneHref }}">{{ $hotline }}</a></dd></div>@endif
                @if(filled($supportEmail))<div><dt>{{ __('theme_contact_page.email') }}</dt><dd><a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></dd></div>@endif
                @if(filled($supportAddress))<div><dt>{{ __('theme_contact_page.address') }}</dt><dd>{{ $supportAddress }}</dd></div>@endif
            </dl>
        </div>
        <div class="xd4-contact__card">
            <h3>{{ $content['form_title'] ?? __('theme_contact_page.message_heading') }}</h3>
            <p class="xd4-contact__hint">{{ __('theme_contact_page.required_hint') }}</p>
            @if(session('contact_status'))<p class="xd4-contact__notice" role="status">{{ session('contact_status') }}</p>@endif
            @include('theme-xd0304::partials.contact-form')
        </div>
    </div>
</section>
