@extends('theme-dn302::layout')
@php($contentEntry = $post ?? $entry ?? null)
@section('title', $pageTitle ?? data_get($contentEntry, 'title', 'Tin tức'))
@section('content') @include('theme-dn302::partials.content-shell', ['title' => data_get($contentEntry, 'title'), 'summary' => data_get($contentEntry, 'excerpt'), 'cover' => data_get($contentEntry, 'featuredMedia.file_url'), 'body' => data_get($contentEntry, 'body')]) @endsection
