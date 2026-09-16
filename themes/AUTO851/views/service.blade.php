@php($pageTitle = data_get($service ?? null, 'title', data_get($entry ?? null, 'title', 'Dịch vụ')))
@php($content = data_get($service ?? null, 'content', data_get($entry ?? null, 'content')))
@php($image = data_get($service ?? null, 'image', data_get($entry ?? null, 'image')))
@include('theme-auto851::partials.content')
