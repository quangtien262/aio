@extends('theme-dn202::layout')
@section('title', $pageTitle ?? 'Dịch vụ')
@section('content') @include('theme-dn202::partials.listing', ['title' => $pageTitle ?? 'Dịch vụ']) @endsection
