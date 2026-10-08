<form class="xd20-contact-form" data-contact-form method="POST" action="{{ route('site.contact.submit', ['locale' => app()->getLocale()]) }}">
    @csrf
    <input type="hidden" name="source" value="contact">
    <p class="xd20-contact-hint">{{ __('theme_contact_page.required_hint') }}</p>
    <div class="xd20-contact-fields">
        @foreach(['name' => [__('theme_contact_page.name'), 'text', 'name', 120, true], 'phone' => [__('theme_contact_page.phone_number'), 'tel', 'tel', 30, false], 'email' => [__('theme_contact_page.email'), 'email', 'email', 150, true], 'subject' => [__('theme_contact_page.subject'), 'text', 'off', 150, false]] as $field => [$label, $type, $autocomplete, $max, $required])
            <div>
                <label for="{{ $formId }}-{{ $field }}">{{ $label }}@if($required)<span aria-hidden="true"> *</span>@endif</label>
                <input id="{{ $formId }}-{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" maxlength="{{ $max }}" value="{{ old($field) }}" @required($required)>
                @error($field)<small class="xd20-contact-server-error" role="alert">{{ $message }}</small>@enderror
            </div>
        @endforeach
        <div class="xd20-contact-wide">
            <label for="{{ $formId }}-message">{{ __('theme_contact_page.message') }}<span aria-hidden="true"> *</span></label>
            <textarea id="{{ $formId }}-message" name="message" rows="4" minlength="10" maxlength="5000" required aria-describedby="{{ $formId }}-hint">{{ old('message') }}</textarea>
            <small id="{{ $formId }}-hint">{{ __('theme_contact_page.message_hint') }}</small>
            @error('message')<small class="xd20-contact-server-error" role="alert">{{ $message }}</small>@enderror
        </div>
    </div>
    <button class="xd20-button" type="submit">{{ data_get($contact, 'data.button_label') ?: __('theme_contact_page.send') }} <span aria-hidden="true">↗</span></button>
</form>
