@extends('theme-ec917::layout')
@section('title', 'Liên hệ')
@section('content')
<main><section class="ec17-inner-hero"><div class="ec17-container"><h1>Liên hệ</h1><p>Đội ngũ EGA Furniture luôn sẵn sàng tư vấn không gian phù hợp.</p></div></section><section class="ec17-content"><div class="ec17-container ec17-prose">{!! data_get($pageModel ?? $page ?? null, 'body', '<p>Vui lòng liên hệ hotline hoặc email để được hỗ trợ.</p>') !!}</div></section></main>
@endsection
