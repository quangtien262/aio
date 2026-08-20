<?php

namespace App\Http\Middleware;

use App\Support\FrontendLocalization;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InjectLandingAdminEditor
{
    public function __construct(
        private readonly LandingPageBuilder $landingPageBuilder,
        private readonly SiteContext $siteContext,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->routeIs('site.home', 'site.landing.show') || ! $response->isSuccessful()) {
            return $response;
        }

        $html = (string) $response->getContent();
        if ($html === '' || ! str_contains(strtolower($html), '</body>')) {
            return $response;
        }

        $themeKey = (string) $this->siteContext->themeKey();
        if (! $this->landingPageBuilder->supportsTheme($themeKey)) {
            return $response;
        }

        $landingPage = $request->routeIs('site.landing.show')
            ? $this->landingPageBuilder->resolveBySlug(
                $this->siteContext->websiteKey(),
                (string) $request->route('slug'),
                $themeKey,
                false,
            )
            : $this->landingPageBuilder->resolveHome($this->siteContext->websiteKey(), $themeKey);

        if (! $landingPage) {
            return $response;
        }

        $viewData = $this->landingPageBuilder->viewData(
            $landingPage,
            app()->getLocale(),
            FrontendLocalization::defaultLocale(),
            $request->user('admin') !== null && $request->query('mod') === 'admin',
        );
        $blocks = collect($viewData['landingBlocks'] ?? [])
            ->filter(fn (array $block): bool => filled($block['id'] ?? null))
            ->values();

        if ($blocks->isEmpty()) {
            return $response;
        }

        if (! str_contains($html, 'data-landing-block-order')) {
            $orderScript = view('site.partials.landing-block-order', [
                'landingBlocks' => $blocks,
            ])->render();
            $html = Str::replaceLast('</body>', $orderScript.'</body>', $html);
            $response->setContent($html);
        }

        if (! $request->user('admin') || $request->query('mod') !== 'admin') {
            return $response;
        }

        $hasButtons = preg_match(
            '/<(?:button|a)\b[^>]*\bdata-xd-edit-block\b[^>]*>/i',
            $html,
        ) === 1;
        $hasEditor = str_contains($html, 'data-xd-editor');
        $hasEditorScript = str_contains($html, 'data-xd-editor-runtime');

        if ($hasButtons && $hasEditor && $hasEditorScript) {
            return $response;
        }

        $editorLocales = collect(FrontendLocalization::localeOptions())
            ->filter(fn (array $locale): bool => (bool) (
                $locale['is_enabled_for_editing']
                ?? $locale['is_active']
                ?? $locale['active']
                ?? false
            ))
            ->map(fn (array $locale): array => [
                'code' => $locale['code'] ?? '',
                'label' => $locale['native_name']
                    ?? $locale['name']
                    ?? $locale['label']
                    ?? ($locale['code'] ?? ''),
                'is_published' => (bool) ($locale['is_published'] ?? false),
            ])
            ->filter(fn (array $locale): bool => filled($locale['code']))
            ->values()
            ->all();

        $injection = view('site.partials.landing-admin-editor', [
            'landingBlocks' => $blocks,
            'blockPayload' => $blocks->keyBy('id')->toArray(),
            'blockUpdateUrlTemplate' => route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']),
            'blockSourcePreviewUrlTemplate' => route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']),
            'editorLocales' => $editorLocales,
            'hasButtons' => $hasButtons,
            'hasEditor' => $hasEditor,
            'hasEditorScript' => $hasEditorScript,
        ])->render();

        $response->setContent(Str::replaceLast('</body>', $injection.'</body>', $html));

        return $response;
    }
}
