@extends('theme-dn202::layout')
@php($contentEntry = $service ?? $entry ?? null)
@section('title', $pageTitle ?? data_get($contentEntry, 'title', 'Dịch vụ'))
@section('content') @include('theme-dn202::partials.content-shell', ['title' => data_get($contentEntry, 'title'), 'summary' => data_get($contentEntry, 'summary'), 'cover' => data_get($contentEntry, 'featuredImage.image_url'), 'body' => data_get($contentEntry, 'body')]) @endsection
