@extends('theme-bds701::layout')
@section('title', data_get($contentEntry, 'title'))
@section('content')
<section class="bds-inner-hero"><div class="bds-container"><p>TIN THỊ TRƯỜNG</p><h1>{{ data_get($contentEntry, 'title') }}</h1></div></section>
<main class="bds-section"><article class="bds-container bds-content-card">@if(data_get($contentEntry, 'featuredImage.image_url'))<img style="width:100%;max-height:560px;object-fit:cover" src="{{ data_get($contentEntry, 'featuredImage.image_url') }}" alt="">@endif{!! data_get($contentEntry, 'body') !!}</article></main>
@endsection
