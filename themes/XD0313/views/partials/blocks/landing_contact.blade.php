@php
    $contactId = 'rx13-contact-'.($block['id'] ?? 'home');
    $contactErrors = session('errors', new \Illuminate\Support\ViewErrorBag);
@endphp

<section id="{{ $anchor }}" class="rx13-section rx13-contact xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}" aria-labelledby="{{ $contactId }}-title">
    {!! $editButton !!}
    <div class="rx13-container">
        <div class="rx13-contact__shell">
            <div class="rx13-contact__copy">
                <p class="rx13-contact__eyebrow">{{ $data['subtitle'] ?? __('XD0313.contact.subtitle') }}</p>
                <h2 id="{{ $contactId }}-title">{{ $data['title'] ?? __('XD0313.contact.title') }}</h2>
                <p class="rx13-contact__intro">{{ $data['description'] ?? __('XD0313.contact.description') }}</p>
                <dl class="rx13-contact__details">
                    @if(filled($hotline ?? null))
                        <div><dt>{{ __('theme_contact_page.phone') }}</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}">{{ $hotline }}</a></dd></div>
                    @endif
                    @if(filled($supportEmail ?? null))
                        <div><dt>{{ __('theme_contact_page.email') }}</dt><dd><a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></dd></div>
                    @endif
                    @if(filled($supportAddress ?? null))
                        <div><dt>{{ __('theme_contact_page.address') }}</dt><dd>{{ $supportAddress }}</dd></div>
                    @endif
                </dl>
                @if(filled($content['note_title'] ?? null) || filled($content['note_text'] ?? null))
                    <div class="rx13-contact__note">
                        @if(filled($content['note_title'] ?? null))<h3>{{ $content['note_title'] }}</h3>@endif
                        @if(filled($content['note_text'] ?? null))<p>{{ $content['note_text'] }}</p>@endif
                    </div>
                @endif
            </div>
            <div class="rx13-contact__card">
                <h3>{{ $content['form_title'] ?? __('XD0313.contact.form_title') }}</h3>
                <p class="rx13-contact__hint">{{ __('theme_contact_page.required_hint') }}</p>
                @if(session('contact_status'))
                    <div class="rx13-contact__notice" role="status" data-rx13-contact-feedback>{{ session('contact_status') }}</div>
                @endif
                @if($contactErrors->any() && old('source') === 'contact')
                    <div class="rx13-contact__error" role="alert" data-rx13-contact-feedback>
                        <strong>{{ __('theme_contact_page.errors') }}</strong>
                        <ul>@foreach($contactErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <form class="rx13-contact__form" method="POST" action="{{ route('site.contact.submit', ['locale' => app()->getLocale()]) }}">
                    @csrf
                    <input type="hidden" name="source" value="contact">
                    <div class="rx13-contact__fields">
                        @foreach(['name' => [__('theme_contact_page.name'), 'text', 'name', 120, true], 'phone' => [__('theme_contact_page.phone_number'), 'tel', 'tel', 30, false], 'email' => [__('theme_contact_page.email'), 'email', 'email', 150, true], 'subject' => [__('theme_contact_page.subject'), 'text', 'off', 150, false]] as $field => [$label, $type, $autocomplete, $max, $required])
                            <div>
                                <label for="{{ $contactId }}-{{ $field }}">{{ $label }}@if($required)<span aria-hidden="true"> *</span>@endif</label>
                                <input id="{{ $contactId }}-{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" maxlength="{{ $max }}" value="{{ old($field) }}" @required($required) @if($contactErrors->has($field) && old('source') === 'contact') aria-invalid="true" @endif>
                            </div>
                        @endforeach
                        <div class="rx13-contact__wide">
                            <label for="{{ $contactId }}-message">{{ __('theme_contact_page.message') }}<span aria-hidden="true"> *</span></label>
                            <textarea id="{{ $contactId }}-message" name="message" rows="4" minlength="10" maxlength="5000" required aria-describedby="{{ $contactId }}-hint" @if($contactErrors->has('message') && old('source') === 'contact') aria-invalid="true" @endif>{{ old('message') }}</textarea>
                            <small id="{{ $contactId }}-hint">{{ __('theme_contact_page.message_hint') }}</small>
                        </div>
                    </div>
                    <button type="submit">{{ $data['button_label'] ?? __('theme_contact_page.send') }} <span aria-hidden="true">↗</span></button>
                </form>
            </div>
        </div>
    </div>
</section>
