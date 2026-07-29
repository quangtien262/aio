<?php

namespace App\Providers;

use App\Core\Themes\ThemeTranslationService;
use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\Demo\Ser102DemoContentProvider;
use App\Core\Themes\Demo\Shop601DemoContentProvider;
use App\Core\Themes\Demo\Shop602DemoContentProvider;
use App\Core\Themes\Demo\Shop603DemoContentProvider;
use App\Core\Themes\Demo\Shop604DemoContentProvider;
use App\Core\Themes\Demo\Shop605DemoContentProvider;
use App\Core\Themes\Demo\Ca0050DemoContentProvider;
use App\Core\Themes\Demo\Ec900DemoContentProvider;
use App\Core\Themes\Demo\Ec901DemoContentProvider;
use App\Core\Themes\Demo\Ec902DemoContentProvider;
use App\Core\Themes\Demo\Ec903DemoContentProvider;
use App\Core\Themes\Demo\Ec904DemoContentProvider;
use App\Core\Themes\Demo\Ec905DemoContentProvider;
use App\Core\Themes\Demo\Ec906DemoContentProvider;
use App\Core\Themes\Demo\Ec907DemoContentProvider;
use App\Core\Themes\Demo\Ec908DemoContentProvider;
use App\Core\Themes\Demo\Ec909DemoContentProvider;
use App\Core\Themes\Demo\Ec910DemoContentProvider;
use App\Core\Themes\Demo\Ec911DemoContentProvider;
use App\Core\Themes\Demo\Ec912DemoContentProvider;
use App\Core\Themes\Demo\Ec913DemoContentProvider;
use App\Core\Themes\Demo\Ec914DemoContentProvider;
use App\Core\Themes\Demo\Ec915DemoContentProvider;
use App\Core\Themes\Demo\Ec916DemoContentProvider;
use App\Core\Themes\Demo\Book920DemoContentProvider;
use App\Core\Themes\Demo\Ec917DemoContentProvider;
use App\Core\Themes\Demo\Spa111DemoContentProvider;
use App\Core\Themes\Demo\Nt502DemoContentProvider;
use App\Core\Themes\Demo\Nt503DemoContentProvider;
use App\Core\Themes\Demo\Th0050DemoContentProvider;
use App\Core\Themes\Demo\Dn202DemoContentProvider;
use App\Core\Themes\Demo\Dn350DemoContentProvider;
use App\Core\Themes\Demo\Dn351DemoContentProvider;
use App\Core\Themes\Demo\Ser103DemoContentProvider;
use App\Core\Themes\Demo\Bds701DemoContentProvider;
use App\Core\Themes\Demo\Xd0302DemoContentProvider;
use App\Core\Themes\Demo\Xd0303DemoContentProvider;
use App\Core\Themes\Demo\Xd0304DemoContentProvider;
use App\Core\Themes\Demo\Xd0305DemoContentProvider;
use App\Core\Themes\Demo\Xd0306DemoContentProvider;
use App\Core\Themes\Demo\Xd0307DemoContentProvider;
use App\Core\Themes\Demo\Xd0308DemoContentProvider;
use App\Core\Themes\Demo\Xd0309DemoContentProvider;
use App\Core\Themes\Demo\Xd0310DemoContentProvider;
use App\Core\Themes\Demo\Xd0311DemoContentProvider;
use App\Core\Themes\Demo\Xd0312DemoContentProvider;
use App\Core\Themes\Demo\Xd0322DemoContentProvider;
use App\Core\Themes\Demo\Xd0323DemoContentProvider;
use App\Core\Themes\Demo\Xd321DemoContentProvider;
use App\Support\FrontendLocalization;
use App\Support\SiteContext;
use App\Models\Admin;
use App\Models\Permission;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SiteContext::class);

        $this->app->singleton(ThemeDemoContentProviderRegistry::class, fn () => new ThemeDemoContentProviderRegistry([
            $this->app->make(Nt502DemoContentProvider::class),
            $this->app->make(Nt503DemoContentProvider::class),
            $this->app->make(Shop602DemoContentProvider::class),
            $this->app->make(Shop603DemoContentProvider::class),
            $this->app->make(Shop604DemoContentProvider::class),
            $this->app->make(Shop605DemoContentProvider::class),
            $this->app->make(Ca0050DemoContentProvider::class),
            $this->app->make(Ec900DemoContentProvider::class),
            $this->app->make(Ec901DemoContentProvider::class),
            $this->app->make(Ec902DemoContentProvider::class),
            $this->app->make(Ec903DemoContentProvider::class),
            $this->app->make(Ec904DemoContentProvider::class),
            $this->app->make(Ec905DemoContentProvider::class),
            $this->app->make(Ec906DemoContentProvider::class),
            $this->app->make(Ec907DemoContentProvider::class),
            $this->app->make(Ec908DemoContentProvider::class),
            $this->app->make(Ec909DemoContentProvider::class),
            $this->app->make(Ec910DemoContentProvider::class),
            $this->app->make(Ec911DemoContentProvider::class),
            $this->app->make(Ec912DemoContentProvider::class),
            $this->app->make(Ec913DemoContentProvider::class),
            $this->app->make(Ec914DemoContentProvider::class),
            $this->app->make(Ec915DemoContentProvider::class),
            $this->app->make(Ec916DemoContentProvider::class),
            $this->app->make(Book920DemoContentProvider::class),
            $this->app->make(Ec917DemoContentProvider::class),
            $this->app->make(Spa111DemoContentProvider::class),
            $this->app->make(Shop601DemoContentProvider::class),
            $this->app->make(Th0050DemoContentProvider::class),
            $this->app->make(Dn202DemoContentProvider::class),
            $this->app->make(Dn350DemoContentProvider::class),
            $this->app->make(Dn351DemoContentProvider::class),
            $this->app->make(Ser103DemoContentProvider::class),
            $this->app->make(Bds701DemoContentProvider::class),
            $this->app->make(Ser102DemoContentProvider::class),
            $this->app->make(Xd0302DemoContentProvider::class),
            $this->app->make(Xd0303DemoContentProvider::class),
            $this->app->make(Xd0304DemoContentProvider::class),
            $this->app->make(Xd0305DemoContentProvider::class),
            $this->app->make(Xd0306DemoContentProvider::class),
            $this->app->make(Xd0307DemoContentProvider::class),
            $this->app->make(Xd0308DemoContentProvider::class),
            $this->app->make(Xd0309DemoContentProvider::class),
            $this->app->make(Xd0310DemoContentProvider::class),
            $this->app->make(Xd0311DemoContentProvider::class),
            $this->app->make(Xd0312DemoContentProvider::class),
            $this->app->make(Xd321DemoContentProvider::class),
            $this->app->make(Xd0322DemoContentProvider::class),
            $this->app->make(Xd0323DemoContentProvider::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $identity = strtolower(trim((string) ($request->input('login') ?: $request->input('email'))));

            return Limit::perMinute(5)->by($identity.'|'.$request->ip());
        });

        Gate::before(function (mixed $user): ?bool {
            return $user instanceof Admin && $user->isSuperAdmin() ? true : null;
        });

        if (Schema::hasTable('permissions') && Schema::hasColumn('permissions', 'is_active')) {
            Permission::query()
                ->where('is_active', true)
                ->pluck('key')
                ->each(fn (string $permission): mixed => Gate::define(
                    $permission,
                    fn (Admin $admin): bool => $admin->hasPermission($permission),
                ));
        }

        app()->setLocale(FrontendLocalization::defaultLocale());
        URL::defaults(FrontendLocalization::routeParameterDefaults(FrontendLocalization::defaultLocale()));
        Blade::directive('themeT', function (string $expression): string {
            return "<?php echo e(app(".ThemeTranslationService::class."::class)->bladeText((string) data_get(\$activeTheme ?? [], 'key', 'TH0001'), app()->getLocale(), {$expression})); ?>";
        });

        $migrationPaths = collect(File::directories(base_path('modules')))
            ->map(fn (string $modulePath): string => $modulePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations')
            ->filter(fn (string $path): bool => File::isDirectory($path))
            ->values()
            ->all();

        if ($migrationPaths !== []) {
            $this->loadMigrationsFrom($migrationPaths);
        }

        collect(File::directories(base_path('themes')))
            ->each(function (string $themePath): void {
                $viewsPath = $themePath.DIRECTORY_SEPARATOR.'views';

                if (! File::isDirectory($viewsPath)) {
                    return;
                }

                $this->loadViewsFrom($viewsPath, 'theme-'.strtolower(basename($themePath)));

                $langPath = $themePath.DIRECTORY_SEPARATOR.'lang';

                if (File::isDirectory($langPath)) {
                    $this->loadJsonTranslationsFrom($langPath);
                }
            });
    }
}
