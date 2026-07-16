<?php

namespace App\Providers;

use App\Core\Themes\ThemeTranslationService;
use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
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
use App\Core\Themes\Demo\Xd321DemoContentProvider;
use App\Support\FrontendLocalization;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ThemeDemoContentProviderRegistry::class, fn () => new ThemeDemoContentProviderRegistry([
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
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
