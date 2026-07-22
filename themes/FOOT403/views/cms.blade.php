@extends('theme-foot403::layout')
@section('title', data_get($cmsEntry ?? [], 'title', 'HTRestaurant'))
@section('content')<section class="dr-section dr-cms"><div class="dr-container"><h1>{{ data_get($cmsEntry ?? [], 'title', 'Nội dung') }}</h1><div>{!! data_get($cmsEntry ?? [], 'content', data_get($cmsEntry ?? [], 'description', '')) !!}</div></div></section>@endsection
