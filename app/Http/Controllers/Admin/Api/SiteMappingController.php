<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Support\MainWebsiteTemplateSynchronizer;
use App\Support\SiteContentCopier;
use App\Support\SiteContentInitializer;
use App\Support\SiteDataPurger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SiteMappingController
{
    public function index(ThemeRegistry $themeRegistry, ThemeDemoContentGenerator $demoContentGenerator): JsonResponse
    {
        $themes = $this->themeOptions($themeRegistry);

        return response()->json([
            'data' => Site::query()
                ->orderByRaw('domain is null')
                ->orderBy('domain')
                ->orderBy('website_key')
                ->get()
                ->map(fn (Site $site): array => $this->sitePayload($site))
                ->values(),
            'meta' => [
                'themes' => $themes,
                'demo_presets_by_theme' => collect($themes)
                    ->mapWithKeys(fn (array $theme): array => [
                        $theme['key'] => $demoContentGenerator->presetsForTheme($theme['key']),
                    ])
                    ->all(),
            ],
        ]);
    }

    public function store(Request $request, ThemeRegistry $themeRegistry, SiteContentInitializer $initializer): JsonResponse
    {
        $validated = $this->validatedPayload($request, $themeRegistry);
        $theme = $this->resolveTheme($themeRegistry, $validated['theme_key']);

        $initialization = [];

        $site = DB::transaction(function () use ($validated, $theme, $initializer, &$initialization): Site {
            $site = Site::query()->create([
                'domain' => $this->normalizeDomain($validated['domain']),
                'website_key' => $this->normalizeWebsiteKey($validated['website_key']),
                'theme_key' => $theme['key'],
                'name' => $validated['name'] ?? null,
                'status' => $validated['status'],
                'settings' => [],
            ]);

            $this->syncSiteProfile($site, $theme);
            $initialization = $initializer->initialize($site, $validated['content_mode']);

            if ($validated['content_mode'] === SiteContentInitializer::MODE_SAMPLE) {
                $this->markDemoDataCreated($site);
            }

            return $site;
        });

        return response()->json([
            'message' => 'Đã thêm cấu hình domain demo.',
            'data' => $this->sitePayload($site->fresh()),
            'meta' => [
                'initialization' => $initialization,
            ],
        ], 201);
    }

    public function bulkStore(
        Request $request,
        ThemeRegistry $themeRegistry,
        SiteContentInitializer $initializer,
        MainWebsiteTemplateSynchronizer $templateSynchronizer,
    ): JsonResponse {
        $validated = $request->validate([
            'root_domain' => ['required', 'string', 'max:255'],
            'content_mode' => ['nullable', 'string', Rule::in([
                SiteContentInitializer::MODE_BLANK,
                SiteContentInitializer::MODE_SAMPLE,
                SiteContentInitializer::MODE_COPY_MAIN,
            ])],
        ]);

        $rootDomain = $this->normalizeDomain($validated['root_domain']);

        if (! preg_match('/^[A-Za-z0-9.-]+$/', $rootDomain)) {
            throw ValidationException::withMessages([
                'root_domain' => 'Domain chính không hợp lệ.',
            ]);
        }

        $created = [];
        $skipped = [];
        $initializations = [];
        $contentMode = $validated['content_mode'] ?? SiteContentInitializer::MODE_BLANK;

        $themes = $themeRegistry->all();

        foreach ($themes as $theme) {
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

            $site = DB::transaction(function () use ($domain, $websiteKey, $themeKey, $theme, $initializer, $contentMode, &$initializations): Site {
                $site = Site::query()->create([
                    'domain' => $domain,
                    'website_key' => $websiteKey,
                    'theme_key' => $themeKey,
                    'name' => 'Demo '.$themeKey,
                    'status' => 'active',
                    'settings' => [
                        'checklist' => [
                            'demo_data_created' => false,
                        ],
                    ],
                ]);

                $this->syncSiteProfile($site, $theme);
                $initializations[$websiteKey] = $initializer->initialize($site, $contentMode);

                return $site;
            });
            $created[] = $this->sitePayload($site->fresh());
        }

        $templateSync = $templateSynchronizer->supports($rootDomain)
            ? $templateSynchronizer->syncThemes($themes, $rootDomain)
            : null;

        return response()->json([
            'message' => sprintf('Đã tạo %d cấu hình domain, bỏ qua %d cấu hình đã tồn tại.', count($created), count($skipped)),
            'data' => [
                'created' => $created,
                'skipped' => $skipped,
                'initializations' => $initializations,
                'website_templates' => $templateSync,
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

    public function bulkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('sites', 'id')],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $updated = Site::query()
            ->whereIn('id', $validated['ids'])
            ->whereNotNull('domain')
            ->update(['status' => $validated['status']]);

        return response()->json([
            'message' => sprintf('Đã cập nhật trạng thái cho %d cấu hình domain.', $updated),
            'data' => [
                'updated' => $updated,
            ],
        ]);
    }

    public function copyContent(Site $site, Request $request, SiteContentCopier $copier): JsonResponse
    {
        $validated = $request->validate([
            'target_site_id' => [
                'required',
                'integer',
                Rule::exists('sites', 'id')->where(fn ($query) => $query->where('id', '!=', $site->id)),
            ],
        ]);

        $targetSite = Site::query()->findOrFail($validated['target_site_id']);

        abort_if(
            $site->website_key === $targetSite->website_key,
            422,
            'Domain nguồn và domain đích phải khác nhau.',
        );

        $counts = $copier->copy($site->website_key, $targetSite->website_key);

        return response()->json([
            'message' => sprintf(
                'Đã sao chép dữ liệu từ %s sang %s.',
                $site->domain ?: $site->website_key,
                $targetSite->domain ?: $targetSite->website_key,
            ),
            'data' => [
                'source' => $this->sitePayload($site),
                'target' => $this->sitePayload($targetSite),
                'counts' => $counts,
            ],
        ]);
    }

    public function generateDemoData(
        Site $site,
        Request $request,
        ThemeDemoContentGenerator $demoContentGenerator,
        SiteContentInitializer $initializer,
        SiteDataPurger $purger,
    ): JsonResponse {
        $themeKey = $this->siteThemeKey($site);
        abort_if($themeKey === null, 422, 'Domain chưa được gán theme để tạo data test.');

        $site->setAttribute('theme_key', $themeKey);
        $availablePresets = collect($demoContentGenerator->presetsForTheme($themeKey));
        $validated = $request->validate([
            'preset' => ['required', 'string', Rule::in($availablePresets->pluck('key')->all())],
            'reset_all' => ['sometimes', 'boolean'],
        ]);

        try {
            $purged = [];
            $result = DB::transaction(function () use ($site, $validated, $initializer, $purger, &$purged): array {
                if ((bool) ($validated['reset_all'] ?? false)) {
                    $purged = $purger->purge(
                        $site->website_key,
                        includeProfile: false,
                        allowDefaultWebsite: true,
                    );
                }

                $result = $initializer->initialize(
                    $site,
                    SiteContentInitializer::MODE_SAMPLE,
                    $validated['preset'],
                );
                $this->markDemoDataCreated($site);

                return $result;
            });
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => sprintf(
                (bool) ($validated['reset_all'] ?? false)
                    ? 'Đã xóa data cũ và tạo data test mới cho %s.'
                    : 'Đã tạo data test cho %s.',
                $site->domain ?: $site->website_key,
            ),
            'data' => [
                'site' => $this->sitePayload($site),
                'initialization' => $result,
                'purged' => $purged,
            ],
        ]);
    }

    public function updateChecklist(Site $site, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tested' => ['sometimes', 'boolean'],
            'demo_data_created' => ['sometimes', 'boolean'],
        ]);

        abort_if($validated === [], 422, 'Cần chọn ít nhất một trạng thái checklist.');

        $settings = (array) $site->settings;
        $checklist = array_merge((array) ($settings['checklist'] ?? []), $validated);
        $settings['checklist'] = $checklist;
        $site->forceFill(['settings' => $settings])->save();

        return response()->json([
            'message' => 'Đã cập nhật checklist domain.',
            'data' => $this->sitePayload($site->fresh()),
        ]);
    }

    public function destroy(Site $site, Request $request, SiteDataPurger $purger): JsonResponse
    {
        abort_if($site->domain === null, 422, 'Không thể xóa site mặc định.');

        $validated = $request->validate([
            'delete_content' => ['sometimes', 'boolean'],
        ]);

        $websiteKey = $site->website_key;
        $purged = [];

        DB::transaction(function () use ($site, $validated, $websiteKey, $purger, &$purged): void {
            $site->delete();

            if ((bool) ($validated['delete_content'] ?? false)) {
                $purged = $purger->purge($websiteKey);
            }
        });

        return response()->json([
            'message' => 'Đã xóa cấu hình domain demo.',
            'data' => [
                'purged' => $purged,
            ],
        ]);
    }

    public function bulkDestroy(Request $request, SiteDataPurger $purger): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('sites', 'id')],
            'delete_content' => ['sometimes', 'boolean'],
        ]);

        $defaultSelected = Site::query()
            ->whereIn('id', $validated['ids'])
            ->whereNull('domain')
            ->exists();

        abort_if($defaultSelected, 422, 'Không thể xóa site mặc định.');

        $sites = Site::query()
            ->whereIn('id', $validated['ids'])
            ->whereNotNull('domain')
            ->get();

        $purged = [];

        $deleted = DB::transaction(function () use ($sites, $validated, $purger, &$purged): int {
            $count = 0;

            foreach ($sites as $site) {
                $websiteKey = $site->website_key;
                $site->delete();
                $count++;

                if ((bool) ($validated['delete_content'] ?? false)) {
                    $purged[$websiteKey] = $purger->purge($websiteKey);
                }
            }

            return $count;
        });

        return response()->json([
            'message' => sprintf('Đã xóa %d cấu hình domain.', $deleted),
            'data' => [
                'deleted' => $deleted,
                'purged' => $purged,
            ],
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
            'content_mode' => ['nullable', 'string', Rule::in([
                SiteContentInitializer::MODE_BLANK,
                SiteContentInitializer::MODE_SAMPLE,
                SiteContentInitializer::MODE_COPY_MAIN,
            ])],
        ]);

        $validated['domain'] = $this->normalizeDomain($validated['domain']);
        $validated['website_key'] = $this->normalizeWebsiteKey($validated['website_key']);
        $validated['content_mode'] ??= SiteContentInitializer::MODE_BLANK;

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

        app(\App\Support\ThemeBrandingResolver::class)->ensure(
            $site->website_key,
            $site->theme_key,
            $siteProfile->globalBranding(),
        );
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
            'theme_key' => $this->siteThemeKey($site),
            'name' => $site->name,
            'status' => $site->status,
            'checklist' => [
                'tested' => (bool) data_get($site->settings, 'checklist.tested', false),
                'demo_data_created' => (bool) data_get($site->settings, 'checklist.demo_data_created', false),
            ],
            'updated_at' => optional($site->updated_at)->toISOString(),
        ];
    }

    private function siteThemeKey(Site $site): ?string
    {
        $themeKey = trim((string) $site->theme_key);

        if ($themeKey !== '') {
            return $themeKey;
        }

        $profileThemeKey = SiteProfile::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', $site->website_key)
            ->value('active_theme_key');

        $profileThemeKey = trim((string) $profileThemeKey);

        return $profileThemeKey !== '' ? $profileThemeKey : null;
    }

    private function markDemoDataCreated(Site $site): void
    {
        $settings = (array) $site->settings;
        data_set($settings, 'checklist.demo_data_created', true);
        $site->forceFill(['settings' => $settings])->save();
    }
}
