@once
    <link rel="stylesheet" href="{{ asset('css/contact-validation.css') }}">
    <script defer src="{{ asset('js/contact-validation.js') }}" data-contact-validation
        data-endpoint="{{ route('site.contact.submit', ['locale' => app()->getLocale()]) }}"
        data-messages="{{ json_encode(__('contact_validation'), JSON_UNESCAPED_UNICODE) }}"
        data-labels="{{ json_encode(['name' => __('theme_contact_page.name'), 'email' => __('theme_contact_page.email'), 'phone' => __('theme_contact_page.phone_number'), 'subject' => __('theme_contact_page.subject'), 'message' => __('theme_contact_page.message')], JSON_UNESCAPED_UNICODE) }}"></script>
@endonce
