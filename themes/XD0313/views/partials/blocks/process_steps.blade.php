@php
    $steps = collect(data_get($content, 'steps', []))->filter()->values();
@endphp

<section id="{{ $anchorId ?? '' }}" class="rx13-section rx13-process-section">
    @include('theme-xd0313::partials.inline-block-edit', ['block' => $block, 'blockIndex' => $blockIndex ?? 0])
    <div class="rx13-container rx13-process">
        <div class="rx13-process__content">
            @if(filled($subtitle))<p class="rx13-eyebrow">{{ $subtitle }}</p>@endif
            <h2>{{ $title }}</h2>
            <div class="rx13-process__steps">
                @foreach($steps as $index => $step)
                    <article>
                        <span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <div><h3>{{ data_get($step, 'title') }}</h3><p>{{ data_get($step, 'description') }}</p></div>
                    </article>
                @endforeach
            </div>
        </div>
        <div class="rx13-process__media">
            @if(filled(data_get($content, 'image_url')))<img src="{{ data_get($content, 'image_url') }}" alt="{{ $title }}">@endif
        </div>
    </div>
</section>
