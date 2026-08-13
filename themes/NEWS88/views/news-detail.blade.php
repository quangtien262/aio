@php
    $entry = $post ?? $entry ?? null;
    $title = data_get($entry, 'title', 'Tin tức');
    $cover = data_get($entry, 'featuredMedia.file_url');
    $body = data_get($entry, 'body') ?: '<p>'.e(data_get($entry, 'excerpt', __('NEWS88.no_content'))).'</p>';
    $related = collect($relatedPosts ?? [])->take(4);
    $latest = collect($latestPosts ?? [])->take(5);
    $comments = collect($postComments ?? []);
    $commentCount = (int) ($postCommentCount ?? 0);
    $postUrl = fn($item) => route('site.blog.show', ['slug' => data_get($item, 'slug')]);
    $postImage = fn($item) => data_get($item, 'featuredMedia.file_url');
@endphp
@extends('theme-news88::layout')
@section('title', $pageTitle ?? $title)
@section('content')
<main class="n88-article n88-article-detail">
    <div class="n88-article-layout">
        <article class="n88-article-card">
            <small>{{ data_get($entry, 'category.name') }} @if(data_get($entry, 'publish_at')) · {{ data_get($entry, 'publish_at')->format('d/m/Y') }} @endif</small>
            <h1>{{ $title }}</h1>
            @if(filled(data_get($entry, 'excerpt')))<p class="n88-article-lead">{{ data_get($entry, 'excerpt') }}</p>@endif
            @if($cover)<img class="n88-article-cover" src="{{ $cover }}" alt="{{ data_get($entry, 'featuredMedia.alt_text', $title) }}">@endif
            <div class="n88-article-body">{!! $body !!}</div>
        </article>

        <aside class="n88-article-sidebar" aria-label="@themeT('NEWS88.latest_articles', 'Bài viết mới nhất')">
            <header><span>@themeT('NEWS88.updated', 'Mới cập nhật')</span><h2>@themeT('NEWS88.latest_articles', 'Bài viết mới nhất')</h2></header>
            <div class="n88-article-latest">
                @foreach($latest as $item)
                    <article>
                        <a class="n88-latest-thumb" href="{{ $postUrl($item) }}">@if($postImage($item))<img src="{{ $postImage($item) }}" alt="{{ data_get($item, 'title') }}" loading="lazy">@else<i class="fa-regular fa-newspaper"></i>@endif</a>
                        <div><small>{{ data_get($item, 'publish_at')?->format('d/m/Y') }}</small><h3><a href="{{ $postUrl($item) }}">{{ data_get($item, 'title') }}</a></h3></div>
                    </article>
                @endforeach
            </div>
        </aside>
    </div>

    @if($related->isNotEmpty())
        <section class="n88-related" aria-labelledby="n88-related-title">
            <header class="n88-detail-heading"><div><span>@themeT('NEWS88.continue_reading', 'Đọc tiếp')</span><h2 id="n88-related-title">@themeT('NEWS88.related_articles', 'Bài viết liên quan')</h2></div><a href="{{ route('site.blog.index') }}">@themeT('NEWS88.view_all', 'Xem tất cả') <i class="fa-solid fa-arrow-right"></i></a></header>
            <div class="n88-related-grid">
                @foreach($related as $item)
                    <article><a class="n88-related-image" href="{{ $postUrl($item) }}">@if($postImage($item))<img src="{{ $postImage($item) }}" alt="{{ data_get($item, 'title') }}" loading="lazy">@else<i class="fa-regular fa-newspaper"></i>@endif</a><div><small>{{ data_get($item, 'category.name') }} · {{ data_get($item, 'publish_at')?->format('d/m/Y') }}</small><h3><a href="{{ $postUrl($item) }}">{{ data_get($item, 'title') }}</a></h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($item, 'excerpt')), 100) }}</p></div></article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="n88-comments" id="binh-luan" aria-labelledby="n88-comments-title">
        <header class="n88-detail-heading"><div><span>@themeT('NEWS88.discussion', 'Thảo luận')</span><h2 id="n88-comments-title">@themeT('NEWS88.comments', 'Bình luận') <b>{{ $commentCount }}</b></h2></div></header>
        @if(session('comment_success'))<div class="n88-comment-success"><i class="fa-regular fa-circle-check"></i> {{ session('comment_success') }}</div>@endif
        @auth('customer')
            <form class="n88-comment-form" method="post" action="{{ route('site.blog.comments.store', ['post' => $entry->getKey()]) }}">@csrf<label for="n88-comment-body">@themeT('NEWS88.join_discussion', 'Tham gia thảo luận')</label><textarea id="n88-comment-body" name="body" rows="4" maxlength="2000" required placeholder="@themeT('NEWS88.comment_placeholder', 'Chia sẻ ý kiến của bạn...')">{{ old('body') }}</textarea>@error('body')<small class="n88-comment-error">{{ $message }}</small>@enderror<button type="submit"><i class="fa-regular fa-paper-plane"></i> @themeT('NEWS88.post_comment', 'Đăng bình luận')</button></form>
        @else
            <div class="n88-comment-gate"><i class="fa-regular fa-comments"></i><div><h3>@themeT('NEWS88.login_to_comment', 'Đăng nhập để bình luận')</h3><p>@themeT('NEWS88.comment_account_note', 'Bạn cần có tài khoản để tham gia thảo luận và trả lời độc giả khác.')</p></div><a href="{{ route('customer.auth.login') }}">@themeT('NEWS88.login', 'Đăng nhập')</a><a class="is-register" href="{{ route('customer.auth.register') }}">@themeT('NEWS88.register', 'Đăng ký')</a></div>
        @endauth

        <div class="n88-comment-list">
            @forelse($comments as $comment)
                @include('theme-news88::partials.comment', ['comment' => $comment, 'entry' => $entry, 'depth' => 0])
            @empty
                <p class="n88-comments-empty">@themeT('NEWS88.no_comments', 'Chưa có bình luận. Hãy là người đầu tiên chia sẻ ý kiến.')</p>
            @endforelse
        </div>
    </section>
</main>
<script>document.addEventListener('click',function(e){const button=e.target.closest('[data-n88-reply]');if(!button)return;const form=document.querySelector('[data-n88-reply-form="'+button.dataset.n88Reply+'"]');if(form){form.hidden=!form.hidden;if(!form.hidden)form.querySelector('textarea')?.focus();}});</script>
@endsection
