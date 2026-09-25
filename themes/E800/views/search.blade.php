@php $entries=collect($products??[]);$money=fn($v)=>(float)$v>0?number_format((float)$v,0,',','.').'đ':'Liên hệ'; @endphp
@extends('theme-e800::layout')
@section('title','Kết quả tìm kiếm')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
