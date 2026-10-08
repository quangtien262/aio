<dialog id="xd4-consultation" class="xd4-consultation xd4-contact__card" data-xd4-consultation aria-labelledby="xd4-consultation-title">
    <button type="button" class="xd4-consultation__close" data-xd4-consultation-close aria-label="{{ __('theme_contact_page.close') }}">×</button>
    <h3 id="xd4-consultation-title">{{ __('theme_contact_page.consultation_title') }}</h3>
    <p class="xd4-contact__hint">{{ __('theme_contact_page.required_hint') }}</p>
    @include('theme-xd0304::partials.contact-form', ['contactFormId' => 'xd4-consultation', 'data' => []])
</dialog>
