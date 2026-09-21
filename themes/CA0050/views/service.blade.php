@extends('theme-ca0050::layout')
@section('content')
@include('theme-ca0050::partials.editorial', ['article' => $entry, 'isService' => true])
@endsection
