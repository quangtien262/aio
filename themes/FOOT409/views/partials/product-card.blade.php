@php $price=(float)data_get($item,'price',0);$old=(float)data_get($item,'original_price',0);$discount=$old>$price&&$old>0?(int)round((1-$price/$old)*100):0; @endphp
<article class="f409-product" data-f409-reveal>
    @if($discount>0)<span class="f409-discount">-{{ $discount }}%</span>@endif
    <a class="f409-product__image" href="{{ data_get($item,'url','#') }}"><img src="{{ data_get($item,'image','/theme-demo/foot409/promo-feast.png') }}" alt="{{ data_get($item,'alt',data_get($item,'title')) }}"></a>
    <div class="f409-product__content"><h3><a href="{{ data_get($item,'url','#') }}">{{ data_get($item,'title') }}</a></h3><div class="f409-stars">★★★★★</div><p class="f409-price"><strong>{{ $price>0?number_format($price,0,',','.').'đ':'Liên hệ' }}</strong>@if($old>$price)<del>{{ number_format($old,0,',','.') }}đ</del>@endif</p><a class="f409-detail" href="{{ data_get($item,'url','#') }}"><i class="fa-solid fa-basket-shopping"></i> Đặt ngay</a></div>
</article>
