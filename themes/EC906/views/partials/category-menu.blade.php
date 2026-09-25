@foreach($categoryMenuItems as $categoryMenuItem)
    <a href="{{ $categoryMenuItem['url'] ?? route('site.catalog.search') }}" target="{{ $categoryMenuItem['target'] ?? '_self' }}">{{ $categoryMenuItem['label'] ?? '' }}</a>
    @if(!empty($categoryMenuItem['children']))
        <div class="ec96-category-children">@include('theme-ec906::partials.category-menu', ['categoryMenuItems' => $categoryMenuItem['children']])</div>
    @endif
@endforeach
