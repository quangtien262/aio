@php $lines=collect($lines??[]); $t=fn(string $key):string=>app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('DN202',app()->getLocale(),$key); @endphp
@extends('theme-dn202::layout')
@section('title', $t('header.cart'))
@section('content')<main><section class="d202-inner-hero"><div class="d202-container"><h1>{{ $t('header.cart') }}</h1></div></section><section class="d202-content"><div class="d202-container d202-content-card">@forelse($lines as $line)<p><strong>{{ data_get($line,'name',data_get($line,'product.name')) }}</strong> × {{ data_get($line,'quantity',1) }}</p>@empty<p>{{ $t('common.no_data') }}</p>@endforelse<a class="d202-btn" href="{{ route('site.checkout.index') }}">{{ $t('common.checkout') }}</a></div></section></main>@endsection
