@php
    $testimonials = collect($block['dynamic_items'] ?? data_get($content, 'items', []))->filter()->values();
    $image = data_get($content, 'image_url');
@endphp

<section id="{{ $anchorId ?? '' }}" class="rx13-section rx13-testimonial-section">
    @include('theme-xd0313::partials.inline-block-edit', ['block' => $block, 'blockIndex' => $blockIndex ?? 0])
    <div class="rx13-container rx13-testimonial">
        <div class="rx13-testimonial__image">@if(filled($image))<img src="{{ $image }}" alt="{{ $title }}">@endif</div>
        <div class="rx13-testimonial__quote">
            @if(filled($subtitle))<p class="rx13-eyebrow">{{ $subtitle }}</p>@endif
            <h2>{{ $title }}</h2>
            @foreach($testimonials->take(1) as $testimonial)
                <blockquote>{{ data_get($testimonial, 'content', data_get($testimonial, 'quote')) }}</blockquote>
                <div class="rx13-testimonial__person">
                    @if(filled(data_get($testimonial, 'avatar_url')))<img src="{{ data_get($testimonial, 'avatar_url') }}" alt="{{ data_get($testimonial, 'name') }}">@endif
                    <span><strong>{{ data_get($testimonial, 'name') }}</strong><small>{{ data_get($testimonial, 'position', data_get($testimonial, 'company')) }}</small></span>
                </div>
            @endforeach
        </div>
    </div>
</section>
