@php
    $primaryImage = data_get($content, 'image_url');
    $secondaryImage = data_get($content, 'secondary_image_url');
    $features = collect(data_get($content, 'features', []))->filter()->values();
@endphp

<section id="{{ $anchorId ?? '' }}" class="rx13-section rx13-about-section">
    @include('theme-xd0313::partials.inline-block-edit', ['block' => $block, 'blockIndex' => $blockIndex ?? 0])
    <div class="rx13-container rx13-about">
        <div class="rx13-about__media">
            <div class="rx13-about__primary">
                @if(filled($primaryImage))<img src="{{ $primaryImage }}" alt="{{ $title }}">@endif
            </div>
            <div class="rx13-about__experience"><strong>{{ data_get($content, 'experience_value', '25') }}</strong><span>{{ data_get($content, 'experience_label', 'Nam kinh nghiem') }}</span></div>
            <div class="rx13-about__secondary">
                @if(filled($secondaryImage))<img src="{{ $secondaryImage }}" alt="{{ $title }}">@endif
            </div>
        </div>
        <div class="rx13-about__content">
            @if(filled($subtitle))<p class="rx13-eyebrow">{{ $subtitle }}</p>@endif
            <h2>{{ $title }}</h2>
            @if(filled(data_get($content, 'description')))<p class="rx13-about__description">{{ data_get($content, 'description') }}</p>@endif
            @if($features->isNotEmpty())
                <div class="rx13-about__features">
                    @foreach($features as $feature)
                        <article>
                            <span aria-hidden="true">&#10003;</span>
                            <div><h3>{{ data_get($feature, 'title') }}</h3><p>{{ data_get($feature, 'description') }}</p></div>
                        </article>
                    @endforeach
                </div>
            @endif
            <a class="rx13-button rx13-button--outline-dark" href="{{ data_get($content, 'button_url', '#lien-he') }}">{{ data_get($content, 'button_label', 'Doc them') }} <span aria-hidden="true">&rarr;</span></a>
        </div>
    </div>
</section>
