@extends('theme-dn350::layout')
@php($contentEntry = $project ?? $entry ?? null)
@section('title', $pageTitle ?? data_get($contentEntry, 'title', 'Dự án'))
@section('content') @include('theme-dn350::partials.content-shell', ['title' => data_get($contentEntry, 'title'), 'summary' => data_get($contentEntry, 'summary'), 'cover' => data_get($contentEntry, 'featuredImage.image_url'), 'body' => data_get($contentEntry, 'body')]) @endsection
