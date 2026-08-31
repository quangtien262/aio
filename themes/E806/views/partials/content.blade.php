@extends('theme-e806::layout')
@section('title',$pageTitle??data_get($entry??null,'title','Chi tiết'))
@section('content')<main><section class="e806-inner-hero"><div class="e806-container"><h1>{{ $pageTitle??data_get($entry??null,'title','Chi tiết') }}</h1></div></section><article class="e806-inner"><div class="e806-container" style="max-width:1100px;line-height:1.8">@if($image??null)<img src="{{ $image }}" alt="" style="width:100%;max-height:650px;object-fit:cover">@endif{!! $content??data_get($entry??null,'content',data_get($entry??null,'body','Nội dung đang được cập nhật.')) !!}</div></article></main>@endsection


