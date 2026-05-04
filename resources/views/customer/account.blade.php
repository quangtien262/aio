<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'AIO Platform') }} Customer Portal</title>
        @viteReactRefresh
        @vite('resources/customer/src/main.jsx')
    </head>
    <body>
        <div
            id="customer-root"
            data-basename="/{{ app()->getLocale() }}/{{ \App\Support\FrontendLocalization::segment('account', app()->getLocale()) }}"
            data-api-base="/{{ app()->getLocale() }}/{{ \App\Support\FrontendLocalization::segment('account', app()->getLocale()) }}/api"
        ></div>
    </body>
</html>
