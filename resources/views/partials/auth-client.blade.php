@once
    <link rel="stylesheet" href="{{ asset('css/auth-client.css') }}">
    <script defer src="{{ asset('js/auth-client.js') }}" data-auth-client
        data-login="{{ route('customer.auth.store', ['locale' => app()->getLocale()]) }}"
        data-register="{{ route('customer.auth.register.store', ['locale' => app()->getLocale()]) }}"
        data-messages="{{ json_encode(__('auth_ui'), JSON_UNESCAPED_UNICODE) }}"></script>
@endonce
