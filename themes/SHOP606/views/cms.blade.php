@extends('theme-shop606::layout')
@section('title', $entry->title)
@section('content')
@include('theme-shop606::partials.service-styles')
<main class="s606-service-page"><div class="s606-service-wrap"><article class="s606-service-detail">
<header class="s606-service-heading"><h1>{{ $entry->title }}</h1>@if($entry->excerpt)<p>{{ $entry->excerpt }}</p>@endif</header>
<div class="s606-service-prose">{!! $entry->body ?? $entry->content !!}</div>
</article></div></main>
@endsection
