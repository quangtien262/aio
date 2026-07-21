@extends('theme-dn202::layout')
@section('title', $pageTitle ?? 'Tin tức')
@section('content') @include('theme-dn202::partials.listing', ['title' => $pageTitle ?? 'Tin tức']) @endsection
