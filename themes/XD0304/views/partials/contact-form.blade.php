<form data-contact-form class="xd4-contact__form" method="POST" action="{{ route('site.contact.submit', ['locale' => app()->getLocale()]) }}">
    @csrf
    <input type="hidden" name="source" value="contact">
    <div class="xd4-contact__fields">
        @foreach(['name' => [__('theme_contact_page.name'), 'text', 'name', 120, true], 'phone' => [__('theme_contact_page.phone_number'), 'tel', 'tel', 30, false], 'email' => [__('theme_contact_page.email'), 'email', 'email', 150, true], 'subject' => [__('theme_contact_page.subject'), 'text', 'off', 150, false]] as $field => [$label, $type, $autocomplete, $max, $required])
            <div>
                <label for="{{ $contactFormId }}-{{ $field }}">{{ $label }}@if($required)<span aria-hidden="true"> *</span>@endif</label>
                <input id="{{ $contactFormId }}-{{ $field }}" type="{{ $type }}" name="{{ $field }}" autocomplete="{{ $autocomplete }}" maxlength="{{ $max }}" value="{{ old($field) }}" @required($required)>
                @error($field)<small class="xd4-contact__error" role="alert">{{ $message }}</small>@enderror
            </div>
        @endforeach
        <div class="xd4-contact__wide">
            <label for="{{ $contactFormId }}-message">{{ __('theme_contact_page.message') }}<span aria-hidden="true"> *</span></label>
            <textarea id="{{ $contactFormId }}-message" name="message" rows="4" minlength="10" maxlength="5000" required aria-describedby="{{ $contactFormId }}-hint">{{ old('message') }}</textarea>
            <small id="{{ $contactFormId }}-hint">{{ __('theme_contact_page.message_hint') }}</small>
            @error('message')<small class="xd4-contact__error" role="alert">{{ $message }}</small>@enderror
        </div>
    </div>
    <button type="submit" class="xd4-button">{{ $data['button_label'] ?? __('theme_contact_page.send') }} <span aria-hidden="true">↗</span></button>
</form>
