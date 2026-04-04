<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
            });
    }
}
