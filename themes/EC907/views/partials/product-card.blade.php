@php
 $price=(int)data_get($item,'price',0);$original=(int)data_get($item,'original_price',0);$discount=$original>$price&&$price>0?(int)round((1-$price/$original)*100):0;
 $image=data_get($item,'image',data_get($item,'image_url','/theme-demo/ec907/keyboard-white.png'));
@endphp
<article class="ec97-product"><a class="ec97-product-img" href="{{ data_get($item,'url','#') }}"><img src="{{ $image }}" alt="{{ data_get($item,'title',data_get($item,'name','Sản phẩm')) }}" loading="lazy"></a><h3><a href="{{ data_get($item,'url','#') }}">{{ data_get($item,'title',data_get($item,'name','Sản phẩm')) }}</a></h3><div><strong>{{ number_format($price,0,',','.') }}₫</strong>@if($discount)<em>-{{ $discount }}%</em>@endif</div>@if($original>$price)<del>{{ number_format($original,0,',','.') }}₫</del>@endif</article>
