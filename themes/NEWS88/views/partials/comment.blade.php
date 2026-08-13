@php($depth = (int) ($depth ?? 0))
<article class="n88-comment {{ $depth > 0 ? 'is-reply' : '' }}" data-comment-id="{{ $comment->getKey() }}">
    <div class="n88-comment-avatar">{{ mb_strtoupper(mb_substr((string) ($comment->customer?->name ?: '?'), 0, 1)) }}</div>
    <div class="n88-comment-content">
        <header><strong>@if($comment->customer){{ $comment->customer->name }}@else @themeT('NEWS88.former_member', 'Thành viên') @endif</strong><time datetime="{{ $comment->created_at?->toIso8601String() }}">{{ $comment->created_at?->diffForHumans() }}</time></header>
        <p>{{ $comment->body }}</p>
        @auth('customer')@if($depth < 3)<button type="button" data-n88-reply="{{ $comment->getKey() }}"><i class="fa-solid fa-reply"></i> @themeT('NEWS88.reply', 'Trả lời')</button>
        <form class="n88-reply-form" data-n88-reply-form="{{ $comment->getKey() }}" method="post" action="{{ route('site.blog.comments.store', ['post' => $entry->getKey()]) }}" hidden>@csrf<input type="hidden" name="parent_id" value="{{ $comment->getKey() }}"><textarea name="body" rows="3" maxlength="2000" required placeholder="@themeT('NEWS88.reply_placeholder', 'Viết câu trả lời...')"></textarea><div><button type="submit">@themeT('NEWS88.send_reply', 'Gửi trả lời')</button></div></form>@endif @endauth
        @if($depth < 3 && $comment->children->isNotEmpty())<div class="n88-comment-children">@foreach($comment->children as $child)@include('theme-news88::partials.comment', ['comment' => $child, 'entry' => $entry, 'depth' => $depth + 1])@endforeach</div>@endif
    </div>
</article>
