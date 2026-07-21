@extends('theme-dn202::layout')
@php($contentEntry = $page ?? $entry ?? null)
@section('title', $pageTitle ?? data_get($contentEntry, 'title', 'DN202 Arc'))
@section('content') @include('theme-dn202::partials.content-shell', ['title' => data_get($contentEntry, 'title'), 'summary' => data_get($contentEntry, 'excerpt'), 'cover' => data_get($contentEntry, 'featuredMedia.file_url'), 'body' => data_get($contentEntry, 'body')]) @endsection
