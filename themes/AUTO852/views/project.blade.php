@php($pageTitle = data_get($project ?? null, 'title', data_get($entry ?? null, 'title', 'Dự án')))
@php($content = data_get($project ?? null, 'content', data_get($entry ?? null, 'content')))
@php($image = data_get($project ?? null, 'image', data_get($entry ?? null, 'image')))
@include('theme-auto852::partials.content')
