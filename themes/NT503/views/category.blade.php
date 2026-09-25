@php $entries=collect($entries??[]);@endphp @extends('theme-nt503::layout') @section('title',data_get($category??null,'name','Sản phẩm')) @section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
