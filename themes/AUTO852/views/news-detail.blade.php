@php($pageTitle = data_get($post ?? null, 'title', data_get($entry ?? null, 'title', 'Tin tức')))
@php($content = data_get($post ?? null, 'body', data_get($entry ?? null, 'body')))
@php($image = data_get($post ?? null, 'image', data_get($entry ?? null, 'image')))
@include('theme-auto852::partials.content')
