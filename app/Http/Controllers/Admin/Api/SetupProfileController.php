<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\SiteProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SetupProfileController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'website_type' => ['required', 'string', Rule::in(array_keys(config('aio.website_types', [])))],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_description' => ['nullable', 'string', 'max:1000'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'primary_color_deep' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'accent_soft_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'background_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'surface_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'surface_tint_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'favicon_url' => ['nullable', 'url', 'max:2048'],
            'support_hotline' => ['nullable', 'string', 'max:120'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_location' => ['nullable', 'string', 'max:120'],
            'boc_status' => ['nullable', 'string', Rule::in(['notified', 'not_notified', 'pending'])],
            'boc_confirmation_url' => ['nullable', 'url', 'max:2048'],
            'boc_footer_note' => ['nullable', 'string', 'max:500'],
        ]);

        $siteProfile = SiteProfile::query()->firstOrNew();
        $completedSteps = collect($siteProfile->completed_steps ?? [])
            ->push('website_type')
            ->unique()
            ->values();

        $paletteFields = [
            'primary_color',
            'primary_color_deep',
            'accent_color',
            'accent_soft_color',
            'background_color',
            'surface_color',
            'surface_tint_color',
        ];

        if (
            filled($validated['company_name'] ?? null)
            || filled($validated['description'] ?? null)
            || filled($validated['company_description'] ?? null)
            || filled($validated['boc_status'] ?? null)
            || collect($paletteFields)->contains(fn (string $field): bool => filled($validated[$field] ?? null))
        ) {
            $completedSteps->push('branding');
        }

        $brandingFields = [
            'company_name',
            'company_description',
            'slogan',
            'primary_color',
            'primary_color_deep',
            'accent_color',
            'accent_soft_color',
            'background_color',
            'surface_color',
            'surface_tint_color',
            'logo_url',
            'favicon_url',
            'support_hotline',
            'support_email',
            'support_location',
            'boc_status',
            'boc_confirmation_url',
            'boc_footer_note',
        ];

        $branding = [];

        foreach ($brandingFields as $field) {
            if ($request->exists($field)) {
                $branding[$field] = $validated[$field] ?? null;
            }
        }

        if ($request->exists('favicon_url') && filled($validated['favicon_url'] ?? null)) {
            $this->publishFavicon((string) $validated['favicon_url']);
            $branding['favicon_url'] = url('/favicon.ico');
        }

        $siteProfile->forceFill([
            'site_name' => $validated['site_name'],
            'description' => $request->exists('description') ? ($validated['description'] ?? null) : $siteProfile->description,
            'website_type' => $validated['website_type'],
            'branding' => array_merge($siteProfile->branding ?? [], $branding),
            'completed_steps' => $completedSteps->unique()->values()->all(),
        ])->save();

        return response()->json([
            'message' => 'Đã lưu cấu hình website.',
        ]);
    }

    private function publishFavicon(string $sourceUrl): void
    {
        $sourcePath = $this->resolvePublicFilePath($sourceUrl);
        $targetPath = public_path('favicon.ico');

        if ($sourcePath && is_file($sourcePath)) {
            if (realpath($sourcePath) === realpath($targetPath)) {
                return;
            }

            if (is_file($targetPath)) {
                @unlink($targetPath);
            }

            copy($sourcePath, $targetPath);

            return;
        }

        $response = Http::timeout(10)->get($sourceUrl);

        if (! $response->successful() || $response->body() === '') {
            throw ValidationException::withMessages([
                'favicon_url' => 'Không thể lấy file favicon đã chọn.',
            ]);
        }

        if (is_file($targetPath)) {
            @unlink($targetPath);
        }

        file_put_contents($targetPath, $response->body());
    }

    private function resolvePublicFilePath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $relativePath = ltrim(rawurldecode($path), '/');
        $absolutePath = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        $publicRoot = realpath(public_path());
        $absoluteRealPath = realpath($absolutePath);

        if (! $publicRoot || ! $absoluteRealPath || ! str_starts_with($absoluteRealPath, $publicRoot)) {
            return null;
        }

        return $absoluteRealPath;
    }
}
