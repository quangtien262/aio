<?php

use App\Support\ThemeBrandingResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_theme_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('website_key', 120);
            $table->string('theme_key', 120);
            $table->json('branding')->nullable();
            $table->timestamps();

            $table->unique(['website_key', 'theme_key']);
            $table->index('theme_key');
        });

        $themeKeys = collect(File::directories(base_path('themes')))
            ->map(function (string $directory): ?string {
                $path = $directory.DIRECTORY_SEPARATOR.'theme.json';

                if (! File::exists($path)) {
                    return null;
                }

                $manifest = json_decode(File::get($path), true);
                $key = strtoupper(trim((string) ($manifest['key'] ?? '')));

                return $key !== '' ? $key : null;
            })
            ->filter()
            ->unique()
            ->values();

        if ($themeKeys->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];

        DB::table('site_profiles')
            ->select(['website_key', 'branding'])
            ->orderBy('id')
            ->each(function (object $profile) use ($themeKeys, $now, &$rows): void {
                $legacyBranding = json_decode((string) $profile->branding, true);
                $publicBranding = array_intersect_key(
                    is_array($legacyBranding) ? $legacyBranding : [],
                    array_flip(ThemeBrandingResolver::PUBLIC_FIELDS),
                );
                $branding = array_merge($publicBranding, ThemeBrandingResolver::COMPANY_DEFAULTS);

                foreach ($themeKeys as $themeKey) {
                    $rows[] = [
                        'website_key' => (string) $profile->website_key,
                        'theme_key' => $themeKey,
                        'branding' => json_encode($branding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($rows) >= 500) {
                        DB::table('site_theme_profiles')->insertOrIgnore($rows);
                        $rows = [];
                    }
                }
            });

        if ($rows !== []) {
            DB::table('site_theme_profiles')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_theme_profiles');
    }
};
