@php
$title=data_get($item,'title','Sản phẩm Sudes');$url=data_get($item,'url','#');$img=data_get($item,'image',data_get($item,'image_url','/theme-demo/ca0050/hero-goldfish.png'));$price=(int)data_get($item,'price',0);$old=(int)data_get($item,'original_price',0);$discount=$old>$price&&$old>0?(int)round((1-$price/$old)*100):0;
@endphp
<article class="ca50-product-card"><a class="ca50-product-image" href="{{ $url }}"><img src="{{ $img }}" alt="{{ $title }}">@if($discount)<span>⚡ DEAL SHOCK</span>@endif</a><div><h3><a href="{{ $url }}">{{ $title }}</a></h3><strong>{{ number_format($price,0,',','.') }}₫</strong>@if($old>$price)<del>{{ number_format($old,0,',','.') }}₫</del><em>- {{ $discount }}%</em>@endif</div></article>
