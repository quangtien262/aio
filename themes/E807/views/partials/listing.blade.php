@extends('theme-e807::layout')
@section('title',$pageTitle??'Nội dung')
@section('content')<main><section class="e807-inner-hero"><div class="e807-container"><h1>{{ $pageTitle??'Nội dung' }}</h1><p>{{ $pageDescription??'' }}</p></div></section><section class="e807-inner"><div class="e807-container e807-products">@forelse($entries??[] as $entry)<article class="e807-product"><a href="{{ data_get($entry,'url','#') }}"><img src="{{ data_get($entry,'image',data_get($entry,'image_url',asset('themes/E807/images/tech-campaign.png'))) }}" alt="{{ data_get($entry,'title',data_get($entry,'name')) }}"></a><h3>{{ data_get($entry,'title',data_get($entry,'name')) }}</h3><strong class="e807-price">{{ (float)data_get($entry,'price')>0?'Giá: '.number_format((float)data_get($entry,'price'),0,',','.').'đ':'' }}</strong><p>{{ \Illuminate\Support\Str::limit(strip_tags((string)data_get($entry,'summary',data_get($entry,'description'))),120) }}</p></article>@empty<p>Nội dung đang được cập nhật.</p>@endforelse</div></section></main>@endsection



