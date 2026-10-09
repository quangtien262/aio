@php
    $items = collect($content['items'] ?? [])->whenEmpty(fn () => collect($block['dynamic_items'] ?? []));
    if (isset($settings['limit'])) $items = $items->take(max(1, (int) $settings['limit']));
@endphp
@once
<style>
.xd5-team-slider{position:relative;margin-top:40px;min-width:0}
.xd5-team-slider .xd5-team{display:flex;gap:30px;margin-top:0;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:thin;scrollbar-color:#f0a629 #edf0f2;padding-bottom:16px;overscroll-behavior-x:contain}
.xd5-team-slider .xd5-team>article{flex:0 0 calc((100% - 60px)/3);min-width:0;scroll-snap-align:start;overflow-wrap:anywhere}
.xd5-team-slider .xd5-team img{display:block;width:100%;height:auto;aspect-ratio:4/5;object-fit:cover}
.xd5-team-controls{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
.xd5-team-controls[hidden]{display:none}
.xd5-team-controls button{display:grid;place-items:center;width:44px;height:44px;border:1px solid #d9dce1;border-radius:50%;background:#fff;color:#191a1d;font-size:24px;cursor:pointer}
.xd5-team-controls button:hover:not(:disabled){background:#f0a629;border-color:#f0a629}
.xd5-team-controls button:disabled{opacity:.35;cursor:default}
.xd5-team-slider :focus-visible{outline:3px solid #98620c;outline-offset:3px}
@media(max-width:900px){.xd5-team-slider .xd5-team>article{flex-basis:calc((100% - 30px)/2)}}
@media(max-width:620px){.xd5-team-slider .xd5-team{gap:20px}.xd5-team-slider .xd5-team>article{flex-basis:88%}}
</style>
@push('scripts')
<script>
(() => {
    document.querySelectorAll('[data-xd5-team-slider]').forEach(slider => {
        const track = slider.querySelector('[data-team-track]');
        const controls = slider.querySelector('.xd5-team-controls');
        const prev = slider.querySelector('[data-team-prev]');
        const next = slider.querySelector('[data-team-next]');
        const update = () => {
            const max = track.scrollWidth - track.clientWidth;
            controls.hidden = max <= 1;
            prev.disabled = track.scrollLeft <= 1;
            next.disabled = track.scrollLeft >= max - 1;
        };
        const move = direction => {
            const card = track.querySelector('article');
            if (!card) return;
            const step = card.getBoundingClientRect().width + (parseFloat(getComputedStyle(track).gap) || 0);
            track.scrollBy({left:direction * step,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
        };
        prev.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        track.addEventListener('keydown', event => {
            if (event.target !== track || !['ArrowLeft','ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            move(event.key === 'ArrowRight' ? 1 : -1);
        });
        track.addEventListener('scroll', update, {passive:true});
        new ResizeObserver(update).observe(track);
        update();
    });
})();
</script>
@endpush
@endonce
@if($items->isNotEmpty())
<section id="{{ $anchor }}" class="xd5-section">
    <div class="xd5-container">
        <header class="xd5-team-head"><p class="xd5-eyebrow">{{ $data['subtitle'] ?? '' }}</p><h2 class="xd5-title">{{ $data['title'] ?? '' }}</h2></header>
        <div class="xd5-team-slider" data-xd5-team-slider>
            <div class="xd5-team" data-team-track tabindex="0" role="region" aria-label="{{ $data['title'] ?? __('xd0305-team.title') }}">
                @foreach($items as $item)
                    <article><img src="{{ $item['image'] ?? 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=800&q=85' }}" alt="{{ $item['name'] ?? $item['title'] ?? '' }}" loading="lazy"><h3>{{ $item['name'] ?? $item['title'] ?? '' }}</h3><p>{{ $item['role'] ?? $item['summary'] ?? '' }}</p></article>
                @endforeach
            </div>
            <div class="xd5-team-controls" hidden><button type="button" data-team-prev aria-label="{{ __('xd0305-team.previous') }}">←</button><button type="button" data-team-next aria-label="{{ __('xd0305-team.next') }}">→</button></div>
        </div>
    </div>
</section>
@endif
