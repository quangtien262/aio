@foreach($nodes as $node)
    <a href="{{ $node['url'] ?? '#' }}" @if($node['current'] ?? false) aria-current="true" @endif>{{ $node['label'] ?? $node['name'] }} @if(isset($node['count']))<small>{{ $node['count'] }}</small>@endif</a>
    @if(!empty($node['children']))<div class="xdc-category-children">@include('themes.common.catalog-categories', ['nodes' => $node['children']])</div>@endif
@endforeach
