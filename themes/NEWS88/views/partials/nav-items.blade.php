@foreach($items as $item)
    @php
        $children = collect(data_get($item, 'children', []))->filter(fn($child) => is_array($child) && filled(data_get($child, 'label')))->values();
        $submenuId = 'n88-submenu-'.($path ?? 'root').'-'.$loop->index;
        $target = data_get($item, 'target', '_self');
    @endphp
    <li class="n88-nav-item">
        <div class="n88-nav-row">
            <a href="{{ data_get($item, 'url', '#') }}" target="{{ $target }}" @if($target === '_blank') rel="noopener noreferrer" @endif>{{ data_get($item, 'label') }}</a>
            @if($children->isNotEmpty())
                <button type="button" class="n88-submenu-toggle" data-n88-submenu-toggle aria-expanded="false" aria-controls="{{ $submenuId }}" aria-label="{{ data_get($item, 'label') }} — menu con"><svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            @endif
        </div>
        @if($children->isNotEmpty())
            <ul class="n88-submenu" id="{{ $submenuId }}" hidden>
                @include('theme-news88::partials.nav-items', ['items' => $children, 'path' => ($path ?? 'root').'-'.$loop->index])
            </ul>
        @endif
    </li>
@endforeach
