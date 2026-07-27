@php
 $price=(int)data_get($item,'price',0);$original=(int)data_get($item,'original_price',0);$discount=$original>$price&&$price>0?(int)round((1-$price/$original)*100):0;
 $image=data_get($item,'image',data_get($item,'image_url','/theme-demo/ec908/whey-black.png'));
@endphp
<article class="ec98-product"><a class="ec98-product-img" href="{{ data_get($item,'url','#') }}"><img src="{{ $image }}" alt="{{ data_get($item,'title',data_get($item,'name','Sản phẩm')) }}" loading="lazy">@if($discount)<em>GIẢM<br>{{ $discount }}%</em>@endif</a><h3><a href="{{ data_get($item,'url','#') }}">{{ data_get($item,'title',data_get($item,'name','Sản phẩm')) }}</a></h3><div><strong>{{ number_format($price,0,',','.') }}₫</strong>@if($original>$price)<del>{{ number_format($original,0,',','.') }}₫</del>@endif</div><p>@if(data_get($item,'badge'))<b>{{ data_get($item,'badge') }}</b>@else<span>NOW</span>@endif {{ data_get($item,'delivery','Giao siêu tốc 2H') }}</p></article>
