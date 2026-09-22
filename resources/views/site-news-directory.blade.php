@extends($directoryLayout)
@section('title', $pageTitle)
@push('head')
<style>
.news-directory{padding:48px 0 72px;background:#f1f2f3;color:#292929}
.news-directory__container{width:min(1360px,calc(100% - 48px));margin-inline:auto}
.news-directory__heading{padding:28px 32px;margin-bottom:28px;background:#fff;border-top:3px solid #e4002b}
.news-directory h1{margin:0;font-size:clamp(26px,4vw,36px)}
.news-directory__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
.news-directory__card{display:flex;flex-direction:column;overflow:hidden;background:#fff;border:1px solid #e4e4e4;box-shadow:0 8px 24px #191f4008}
.news-directory__image{width:100%;height:200px;object-fit:cover}
.news-directory__copy{padding:26px;display:flex;flex:1;flex-direction:column;align-items:flex-start}
.news-directory h2{font-size:21px;line-height:1.45;margin:0 0 12px;overflow-wrap:anywhere}
.news-directory__copy p{margin:0 0 24px;color:#666;line-height:1.7;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;overflow:hidden}
.news-directory__link{margin-top:auto;color:#c90028;font-weight:700}
.news-directory a:hover{text-decoration:underline}.news-directory a:focus-visible{outline:2px solid #e4002b;outline-offset:4px}
.news-directory__empty{padding:32px;background:#fff}
.news-directory__pagination{margin-top:32px}
@media(max-width:900px){.news-directory__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.news-directory{padding:24px 0 40px}.news-directory__container{width:calc(100% - 28px)}.news-directory__grid{grid-template-columns:1fr}.news-directory__heading{padding:24px}.news-directory__copy{padding:22px}}
</style>
@endpush
@section('content')
<main class="news-directory"><div class="news-directory__container">
    <header class="news-directory__heading"><h1>{{ $pageTitle }}</h1></header>
    @if($directoryItems->isEmpty())
        <p class="news-directory__empty">{{ app()->getLocale() === 'en' ? 'No content available yet.' : 'Nội dung đang được cập nhật.' }}</p>
    @else
        <div class="news-directory__grid">
        @foreach($directoryItems as $item)
            <article class="news-directory__card">
                @if($item['image'])<a href="{{ $item['url'] }}" tabindex="-1" aria-hidden="true"><img class="news-directory__image" src="{{ $item['image'] }}" alt="" loading="lazy"></a>@endif
                <div class="news-directory__copy">
                    <h2><a href="{{ $item['url'] }}">{{ $item['name'] }}</a></h2>
                    @if(filled($item['description']))<p>{{ strip_tags($item['description']) }}</p>@endif
                    <a class="news-directory__link" href="{{ $item['url'] }}">{{ app()->getLocale() === 'en' ? 'View articles' : 'Xem bài viết' }} <span aria-hidden="true">→</span></a>
                </div>
            </article>
        @endforeach
        </div>
    @endif
    <div class="news-directory__pagination n88-pagination">{{ $directoryItems->links('pagination::bootstrap-4') }}</div>
</div></main>
@endsection
