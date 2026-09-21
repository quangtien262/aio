<nav class="ca50-inner-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home', ['locale' => app()->getLocale()]) }}">{{ __('Trang chủ') }}</a><span>/</span><span aria-current="page">{{ $heading }}</span></nav>
<header class="ca50-inner-heading"><h1>{{ $heading }}</h1>@if(filled($intro ?? null))<p>{{ $intro }}</p>@endif</header>
