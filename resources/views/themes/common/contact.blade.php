@php
    $branding = (array) data_get($siteProfile ?? [], 'branding', data_get($themeShellData ?? [], 'branding', []));
    $company = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', '')));
    $phone = trim((string) ($branding['support_hotline'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? ''));
    $address = trim((string) ($branding['support_location'] ?? ''));
    $contactBody = data_get($entry ?? $pageModel ?? $page ?? null, 'body', '');
    $pageTitle = __('theme_contact_page.title').($company ? ' | '.$company : '');
    $contactErrors = session('errors', new \Illuminate\Support\ViewErrorBag);
@endphp
@include('themes.common.contact-styles')
<main class="tc-contact-page">
<div class="tc-contact-container">
    <nav class="tc-contact-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home', ['locale' => app()->getLocale()]) }}">{{ __('theme_contact_page.home') }}</a><span>/</span><span aria-current="page">{{ __('theme_contact_page.title') }}</span></nav>
    <div class="tc-contact-heading"><span>{{ $company }}</span><h1>{{ __('theme_contact_page.heading') }}</h1><p>{{ __('theme_contact_page.intro') }}</p></div>
    @if(filled(strip_tags((string) $contactBody)))<div class="tc-contact-copy">{!! $contactBody !!}</div>@endif
    <div class="tc-contact-layout">
        <aside class="tc-contact-info" aria-labelledby="tc-contact-info-title">
            <h2 id="tc-contact-info-title">{{ __('theme_contact_page.information') }}</h2><p>{{ __('theme_contact_page.direct_intro') }}</p>
            @if($phone)<div class="tc-contact-item"><span class="tc-contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 3h4l2 5-3 2c2 4 3 5 7 7l2-3 5 2v4c-10 3-22-9-17-17Z"/></svg></span><div><h3>{{ __('theme_contact_page.phone') }}</h3><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></div></div>@endif
            @if($email)<div class="tc-contact-item"><span class="tc-contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 5h18v14H3Z M3 5l9 7 9-7"/></svg></span><div><h3>{{ __('theme_contact_page.email') }}</h3><a href="mailto:{{ $email }}">{{ $email }}</a></div></div>@endif
            @if($address)<div class="tc-contact-item"><span class="tc-contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-8 8-14a8 8 0 0 0-16 0c0 6 8 14 8 14Z M12 5a3 3 0 1 0 0 6 3 3 0 0 0 0-6"/></svg></span><div><h3>{{ __('theme_contact_page.address') }}</h3><p>{{ $address }}</p><a class="tc-contact-directions" href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($address) }}" target="_blank" rel="noopener noreferrer">{{ __('theme_contact_page.directions') }} ↗</a></div></div>@endif
        </aside>
        <section class="tc-contact-form-card" aria-labelledby="tc-contact-form-title">
            <h2 id="tc-contact-form-title">{{ __('theme_contact_page.message_heading') }}</h2><p>{{ __('theme_contact_page.required_hint') }}</p>
            @if(session('contact_status'))<div class="tc-contact-notice" role="status">{{ session('contact_status') }}</div>@endif
            @if($contactErrors->any())<div class="tc-contact-error" role="alert"><strong>{{ __('theme_contact_page.errors') }}</strong><ul>@foreach($contactErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route('site.contact.submit', ['locale' => app()->getLocale()]) }}">
                @csrf
                <input type="hidden" name="source" value="contact">
                <div class="tc-contact-fields">
                    @foreach(['name' => [__('theme_contact_page.name'), 'text', 'name', 120, true], 'phone' => [__('theme_contact_page.phone_number'), 'tel', 'tel', 30, false], 'email' => [__('theme_contact_page.email'), 'email', 'email', 150, true], 'subject' => [__('theme_contact_page.subject'), 'text', 'off', 150, false]] as $field => [$label, $type, $autocomplete, $max, $required])
                    <div @class(['tc-contact-field-wide' => in_array($field, ['email', 'subject'])])><label for="tc-contact-{{ $field }}">{{ $label }}{{ $required ? ' *' : '' }}</label><input id="tc-contact-{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" maxlength="{{ $max }}" value="{{ old($field) }}" @required($required) @if($contactErrors->has($field)) aria-invalid="true" @endif></div>
                    @endforeach
                    <div class="tc-contact-field-wide"><label for="tc-contact-message">{{ __('theme_contact_page.message') }} *</label><textarea id="tc-contact-message" name="message" rows="5" minlength="10" maxlength="5000" required aria-describedby="tc-contact-hint" @if($contactErrors->has('message')) aria-invalid="true" @endif>{{ old('message') }}</textarea><small id="tc-contact-hint">{{ __('theme_contact_page.message_hint') }}</small></div>
                </div>
                <button type="submit">{{ __('theme_contact_page.send') }} <span aria-hidden="true">→</span></button>
            </form>
        </section>
    </div>
</div>
</main>

