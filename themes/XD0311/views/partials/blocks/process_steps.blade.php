@php $steps = collect(data_get($content, 'items', []))->values(); @endphp
<section id="{{ $anchor }}" class="xd3-process xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="process_steps">
    <div class="xd3-container">
        <div class="xd3-process__title">
            <h2>{{ $data['title'] ?? 'Cách chúng tôi hoạt động' }}</h2>
            <p>{{ $data['subtitle'] ?? 'Quy trình làm việc' }}</p>
        </div>
        <p class="xd3-process__intro">{{ $data['description'] ?? '' }}</p>
        <div class="xd3-steps">
            @foreach($steps as $index => $step)
                <article class="xd3-step">
                    <div class="xd3-step__number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                    <h3>{{ $step['title'] ?? '' }}</h3>
                    <p>{{ $step['description'] ?? '' }}</p>
                </article>
            @endforeach
        </div>
    </div>
    @if($canEditLanding && filled($block['id'] ?? null))
        <button type="button" class="xd-edit-block" data-xd-edit-block="{{ $block['id'] }}">Sửa khối</button>
    @endif
</section>
