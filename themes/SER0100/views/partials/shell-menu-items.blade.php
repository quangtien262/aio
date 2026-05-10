@php
    $items = $items ?? [];
    $depth = $depth ?? 0;
@endphp

<div class="ser-shell-tree-group">
    @foreach ($items as $item)
        @php
            $children = collect($item['children'] ?? [])->filter(fn ($child): bool => is_array($child))->values();
            $label = (string) ($item['label'] ?? $item['name'] ?? 'Dịch vụ');
            $url = (string) ($item['url'] ?? '#');
            $target = (string) ($item['target'] ?? '_self');
        @endphp
        <div class="ser-shell-tree-item">
            <a class="ser-shell-tree-link {{ $depth === 0 ? 'is-root' : 'is-child' }}" href="{{ $url }}" target="{{ $target }}">
                <span class="ser-shell-tree-link-icon {{ $depth === 0 ? 'is-root' : 'is-child' }}" aria-hidden="true">{{ $depth === 0 ? '▸' : '↳' }}</span>
                <span>{{ $label }}</span>
            </a>
            @if ($children->isNotEmpty())
                <div class="ser-shell-tree-children">
                    @include('theme-ser0100::partials.shell-menu-items', ['items' => $children->all(), 'depth' => $depth + 1])
                </div>
            @endif
        </div>
    @endforeach
</div>
