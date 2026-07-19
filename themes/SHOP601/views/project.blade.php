@extends('theme-shop601::layout')
@section('title', data_get($project ?? null, 'title', 'Dự án'))
@section('content') @include('theme-shop601::partials.content-shell') @endsection
