@php
    $cover = $isService ? $article->featuredImage?->image_url : $article->featuredMedia?->file_url;
    $summary = $isService ? $article->summary : $article->excerpt;
    $body = $isService ? $article->content : $article->body;
@endphp
<main class="ca50-inner-page"><div class="ca50-inner-container">
    @include('theme-ca0050::partials.inner-heading', ['heading' => $article->title, 'intro' => $summary])
    <article class="ca50-editorial">
        @if($cover)<img class="ca50-editorial-cover" src="{{ $cover }}" alt="{{ $article->title }}">@endif
        <div class="ca50-editorial-body">@if(filled($body)){!! $body !!}@else<p>{{ __('Nội dung đang được cập nhật.') }}</p>@endif</div>
    </article>
    <section class="ca50-inner-cta"><div><h2>{{ __('Cùng tạo nên không gian thủy sinh của bạn') }}</h2><p>{{ __('Trao đổi với chúng tôi để được tư vấn sản phẩm và dịch vụ phù hợp.') }}</p></div><a class="ca50-inner-button" href="{{ route('site.contact', ['locale' => app()->getLocale()]) }}">{{ __('Liên hệ tư vấn') }} →</a></section>
</div></main>
