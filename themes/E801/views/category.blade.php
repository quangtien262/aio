@php $entries=collect($products??[]);$money=fn($v)=>(float)$v>0?number_format((float)$v,0,',','.').'đ':'Liên hệ'; @endphp
@extends('theme-e801::layout') @section('title',data_get($category??null,'name','Sản phẩm')) @section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
