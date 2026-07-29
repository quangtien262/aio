@php
    $children = collect(data_get($item, 'children', []))
        ->filter(fn ($child) => is_array($child) && filled(data_get($child, 'label', data_get($child, 'title'))))
        ->values();
    $label = data_get($item, 'label', data_get($item, 'title', ''));
    $target = data_get($item, 'target', '_self');
@endphp
<div class="dn-menu-item {{ $children->isNotEmpty() ? 'has-children' : '' }}" data-dn-menu-level="{{ $level }}">
    <a href="{{ data_get($item, 'url', '#') }}" target="{{ $target }}">
        <span>{{ $label }}</span>
        @if($children->isNotEmpty())<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>@endif
    </a>
    @if($children->isNotEmpty())
        <div class="dn-submenu">
            @foreach($children as $child)
                @include('theme-dn351::partials.menu-item', ['item' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>
