@extends('theme-bds701::layout')
@section('content')<section class="bds-inner-hero"><div class="bds-container"><h1>Kết quả tìm kiếm</h1></div></section><main class="bds-section"><div class="bds-container bds-grid">@foreach(($products ?? collect()) as $item)<article class="bds-content-card"><h3>{{ data_get($item, 'name') }}</h3></article>@endforeach</div></main>@endsection
