<?php

namespace App\Http\Controllers\Customer;

use App\Models\CmsPost;
use App\Models\CmsPostComment;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CmsPostCommentController
{
    public function __invoke(Request $request, SiteContext $siteContext, string $locale, int|string $post): RedirectResponse
    {
        if (! $request->user('customer')) {
            return redirect()->guest(route('customer.auth.login'));
        }

        $post = CmsPost::withoutGlobalScopes()->whereKey($post)->firstOrFail();
        abort_unless($post->website_key === $siteContext->websiteKey() && $post->status === 'published', 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $body = trim(strip_tags((string) $validated['body']));
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Nội dung bình luận không được để trống.']);
        }

        $parent = null;
        if (! empty($validated['parent_id'])) {
            $parent = CmsPostComment::query()
                ->published()
                ->where('website_key', $siteContext->websiteKey())
                ->where('cms_post_id', $post->getKey())
                ->findOrFail((int) $validated['parent_id']);

            $depth = 1;
            $ancestor = $parent;
            while ($ancestor->parent_id !== null && $depth < 4) {
                $ancestor = CmsPostComment::query()->find($ancestor->parent_id);
                $depth++;
            }

            if ($depth >= 4) {
                throw ValidationException::withMessages(['parent_id' => 'Chuỗi trả lời đã đạt độ sâu tối đa.']);
            }
        }

        CmsPostComment::query()->create([
            'website_key' => $siteContext->websiteKey(),
            'cms_post_id' => $post->getKey(),
            'customer_id' => $request->user('customer')->getKey(),
            'parent_id' => $parent?->getKey(),
            'body' => $body,
            'status' => 'published',
        ]);

        return back()->with('comment_success', $parent ? 'Đã gửi câu trả lời.' : 'Đã đăng bình luận.')->withFragment('binh-luan');
    }
}
