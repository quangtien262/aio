@foreach ($items as $item)
    @php
        $children = collect($item['children'] ?? [])
            ->filter(fn ($child): bool => is_array($child) && filled($child['label'] ?? null))
            ->values()
            ->all();
        $hasChildren = $children !== [];
    @endphp

    @if ($mobile)
        <div class="xd2-mobile-menu__item" data-xd-mobile-nav-item data-level="{{ $level }}">
            <div class="xd2-mobile-menu__row">
                <a class="xd2-mobile-link {{ !empty($item['active']) ? 'is-active' : '' }}" href="{{ $item['href'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] ?? 'Menu' }}</a>
                @if ($hasChildren)
                    <button class="xd2-mobile-submenu-toggle" type="button" aria-expanded="false" aria-label="Mở menu con {{ $item['label'] ?? 'Menu' }}" data-xd-submenu-toggle>⌄</button>
                @endif
            </div>
            @if ($hasChildren)
                <div class="xd2-mobile-menu__children" data-xd-submenu hidden>
                    @include('theme-xd0302::partials.navigation-tree', ['items' => $children, 'level' => $level + 1, 'mobile' => true])
                </div>
            @endif
        </div>
    @else
        <li class="xd2-nav__item {{ $hasChildren ? 'has-children' : '' }}" data-xd-desktop-nav-item>
            <div class="xd2-nav__row">
                <a class="xd2-nav__link {{ !empty($item['active']) ? 'is-active' : '' }}" href="{{ $item['href'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] ?? 'Menu' }}</a>
                @if ($hasChildren)
                    <button class="xd2-submenu-toggle" type="button" aria-expanded="false" aria-label="Mở menu con {{ $item['label'] ?? 'Menu' }}" data-xd-submenu-toggle>⌄</button>
                @endif
            </div>
            @if ($hasChildren)
                <ul class="xd2-submenu {{ $level > 0 ? 'is-nested' : '' }}" data-xd-submenu>
                    @include('theme-xd0302::partials.navigation-tree', ['items' => $children, 'level' => $level + 1, 'mobile' => false])
                </ul>
            @endif
        </li>
    @endif
@endforeach
