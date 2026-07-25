@php
$cardImage=data_get($item,'image',data_get($item,'image_url','/theme-demo/shop604/product-women-rose.png'));
$price=(float)data_get($item,'price',0);$old=(float)data_get($item,'original_price',0);
$discount=$old>$price&&$price>0?(int)round((1-$price/$old)*100):null;
$url=data_get($item,'url','#');$title=data_get($item,'title','Sản phẩm Bean Lingerie');
$brand=data_get($item,'summary','BEAN LINGERIE');
@endphp
<article class="s604-product">
    <a class="s604-product-media" href="{{ $url }}">
        <img src="{{ $cardImage }}" alt="{{ $title }}" loading="lazy">
        @if($discount)<b>- {{ $discount }}%</b>@endif
        <span class="s604-tags"><i>Hàng mới</i><i>Bán chạy</i></span>
        <span class="s604-card-actions"><i class="fa-regular fa-heart"></i><i class="fa-solid fa-shuffle"></i></span>
    </a>
    <div class="s604-product-copy">
        <small>{{ strtoupper(\Illuminate\Support\Str::limit(strip_tags((string)$brand),24)) }}</small>
        <h3><a href="{{ $url }}">{{ $title }}</a></h3>
        <p>@if($price>0)<strong>{{ number_format($price,0,',','.') }}đ</strong>@else<strong>@themeT('SHOP604.common.contact_price','Liên hệ')</strong>@endif @if($old>$price)<del>{{ number_format($old,0,',','.') }}đ</del>@endif</p>
    </div>
</article>
