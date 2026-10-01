@foreach($categoryItems as $categoryItem)
    <li>
        <a href="{{ data_get($categoryItem, 'url', route('site.catalog.search')) }}">{{ data_get($categoryItem, 'label') }}</a>
        @if(!empty($categoryItem['children']))
            <ul>@include('theme-ec911::partials.category-items', ['categoryItems' => $categoryItem['children']])</ul>
        @endif
    </li>
@endforeach
