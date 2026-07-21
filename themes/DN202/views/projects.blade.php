@extends('theme-dn202::layout')
@section('title', $pageTitle ?? 'Dự án')
@section('content') @include('theme-dn202::partials.listing', ['title' => $pageTitle ?? 'Dự án']) @endsection
