@php($pageTitle = data_get($page ?? null, 'title', __('Thông tin')))
@php($content = data_get($page ?? null, 'content', data_get($page ?? null, 'body')))
@include('theme-tool751::partials.content')
