<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeRegistry;
use App\Models\Site;
use App\Models\SiteProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiteMappingController
{
    public function index(ThemeRegistry $themeRegistry): JsonResponse
    {
        return response()->json([
            'data' => Site::query()
                ->orderByRaw('domain is null')
                ->orderBy('domain')
                ->orderBy('website_key')
                ->get()
                ->map(fn (Site $site): array => $this->sitePayload($site))
                ->values(),
            'meta' => [
                'themes' => $this->themeOptions($themeRegistry),
            ],
        ]);
    }

    public function store(Request $request, ThemeRegistry $themeRegistry): JsonResponse
    {
        $validated = $this->validatedPayload($request, $themeRegistry);
        $theme = $this->resolveTheme($themeRegistry, $validated['theme_key']);

        $site = Site::query()->create([
            'domain' => $this->normalizeDomain($validated['domain']),
            'website_key' => $this->normalizeWebsiteKey($validated['website_key']),
            'theme_key' => $theme['key'],
            'name' => $validated['name'] ?? null,
            'status' => $validated['status'],
            'settings' => [],
        ]);

        $this->syncSiteProfile($site, $theme);

        return response()->json([
            'message' => 'Đã thêm cấu hình domain demo.',
            'data' => $this->sitePayload($site->fresh()),
        ], 201);
    }

    public function bulkStore(Request $request, ThemeRegistry $themeRegistry): JsonResponse
    {
        $validated = $request->validate([
            'root_domain' => ['required', 'string', 'max:255'],
        ]);

        $rootDomain = $this->normalizeDomain($validated['root_domain']);

        if (! preg_match('/^[A-Za-z0-9.-]+$/', $rootDomain)) {
            throw ValidationException::withMessages([
                'root_domain' => 'Domain chính không hợp lệ.',
            ]);
        }

        $created = [];
        $skipped = [];

        foreach ($themeRegistry->all() as $theme) {
            $themeKey = strtoupper((string) ($theme['key'] ?? ''));

            if ($themeKey === '') {
                continue;
            }

            $domain = $this->normalizeDomain($themeKey.'.'.$rootDomain);
            $websiteKey = Str::slug($domain) ?: strtolower($themeKey);

            $existing = Site::query()
                ->where(fn ($query) => $query->where('domain', $domain)->orWhere('website_key', $websiteKey))
                ->first();

            if ($existing !== null) {
                $skipped[] = $this->sitePayload($existing);
                continue;
            }

            $site = Site::query()->create([
                'domain' => $domain,
                'website_key' => $websiteKey,
                'theme_key' => $themeKey,
                'name' => 'Demo '.$themeKey,
                'status' => 'active',
                'settings' => [],
            ]);

            $this->syncSiteProfile($site, $theme);
            $created[] = $this->sitePayload($site->fresh());
        }

        return response()->json([
            'message' => sprintf('Đã tạo %d cấu hình domain, bỏ qua %d cấu hình đã tồn tại.', count($created), count($skipped)),
            'data' => [
                'created' => $created,
                'skipped' => $skipped,
            ],
        ], 201);
    }

    public function update(Site $site, Request $request, ThemeRegistry $themeRegistry): JsonResponse
    {
        $validated = $this->validatedPayload($request, $themeRegistry, $site);
        $theme = $this->resolveTheme($themeRegistry, $validated['theme_key']);
        $previousWebsiteKey = $site->website_key;

        $site->forceFill([
            'domain' => $this->normalizeDomain($validated['domain']),
            'website_key' => $this->normalizeWebsiteKey($validated['website_key']),
            'theme_key' => $theme['key'],
            'name' => $validated['name'] ?? null,
            'status' => $validated['status'],
        ])->save();

        $this->syncSiteProfile($site, $theme, $previousWebsiteKey);

        return response()->json([
            'message' => 'Đã cập nhật cấu hình domain demo.',
            'data' => $this->sitePayload($site->fresh()),
        ]);
    }

    public function destroy(Site $site): JsonResponse
    {
        abort_if($site->domain === null, 422, 'Không thể xóa site mặc định.');

        $site->delete();

        return response()->json([
            'message' => 'Đã xóa cấu hình domain demo.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ThemeRegistry $themeRegistry, ?Site $site = null): array
    {
        $themeKeys = $themeRegistry->all()->pluck('key')->all();

        $validated = $request->validate([
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9.-]+$/',
                Rule::unique('sites', 'domain')->ignore($site?->id),
            ],
            'website_key' => [
                'required',
                'string',
                'max:120',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('sites', 'website_key')->ignore($site?->id),
            ],
            'theme_key' => ['required', 'string', Rule::in($themeKeys)],
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $validated['domain'] = $this->normalizeDomain($validated['domain']);
        $validated['website_key'] = $this->normalizeWebsiteKey($validated['website_key']);

        return $validated;
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('~[/:?#].*$~', '', $domain) ?? $domain;

        return trim($domain, '.');
    }

    private function normalizeWebsiteKey(string $websiteKey): string
    {
        return trim($websiteKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTheme(ThemeRegistry $themeRegistry, string $themeKey): array
    {
        $theme = $themeRegistry->all()->firstWhere('key', $themeKey);

        if ($theme === null) {
            throw ValidationException::withMessages([
                'theme_key' => 'Theme không tồn tại.',
            ]);
        }

        return $theme;
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    private function syncSiteProfile(Site $site, array $theme, ?string $previousWebsiteKey = null): void
    {
        $siteProfile = SiteProfile::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', $site->website_key)
            ->first();

        if ($siteProfile === null && $previousWebsiteKey !== null && $previousWebsiteKey !== $site->website_key) {
            $siteProfile = SiteProfile::query()
                ->withoutGlobalScope('current_website')
                ->where('website_key', $previousWebsiteKey)
                ->first();
        }

        $siteProfile ??= new SiteProfile();

        $branding = $siteProfile->branding ?? [];
        $branding['website_key'] = $site->website_key;

        $siteProfile->forceFill([
            'website_key' => $site->website_key,
            'site_name' => $site->name ?: ($siteProfile->site_name ?? $site->website_key),
            'website_type' => $siteProfile->website_type ?: ($theme['website_type'] ?? null),
            'active_theme_key' => $site->theme_key,
            'branding' => $branding,
        ])->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function themeOptions(ThemeRegistry $themeRegistry): array
    {
        return $themeRegistry->all()
            ->map(fn (array $theme): array => [
                'key' => $theme['key'],
                'name' => $theme['name'],
                'website_type' => $theme['website_type'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function sitePayload(?Site $site): array
    {
        if ($site === null) {
            return [];
        }

        return [
            'id' => $site->id,
            'domain' => $site->domain,
            'website_key' => $site->website_key,
            'theme_key' => $site->theme_key,
            'name' => $site->name,
            'status' => $site->status,
            'updated_at' => optional($site->updated_at)->toISOString(),
        ];
    }
}
