<?php

namespace App\Core\Modules\Support;

use App\Core\Modules\Support\ModulePackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ModuleLifecycleRunner
{
    public function install(array $module): void
    {
        $package = ModulePackage::fromModule($module);

        $this->runMigrations($package);
        $this->publishConfig($package);
        $this->publishAssets($package);
        $this->runSeeders($package);
    }

    public function upgrade(array $module, ?string $fromVersion): void
    {
        $package = ModulePackage::fromModule($module);

        if ($fromVersion !== null && version_compare($module['latest_version'], $fromVersion, '<=')) {
            return;
        }

        $this->runMigrations($package);
        $this->publishConfig($package);
        $this->publishAssets($package);
        $this->runSeeders($package);
    }

    public function uninstall(array $module): void
    {
        $package = ModulePackage::fromModule($module);

        $this->rollbackMigrations($package);
        $this->removePublishedAssets($package);
        $this->removePublishedConfig($package);
    }

    private function runMigrations(ModulePackage $package): void
    {
        foreach ($package->migrationPaths as $migrationPath) {
            if (! File::isDirectory($migrationPath) || empty(File::files($migrationPath))) {
                continue;
            }

            $this->callArtisan('migrate', [
                '--path' => $migrationPath,
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    private function rollbackMigrations(ModulePackage $package): void
    {
        foreach ($package->migrationPaths as $migrationPath) {
            if (! File::isDirectory($migrationPath) || empty(File::files($migrationPath))) {
                continue;
            }

            $this->callArtisan('migrate:rollback', [
                '--path' => $migrationPath,
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    private function runSeeders(ModulePackage $package): void
    {
        foreach ($package->seeders as $seederClass) {
            $this->ensureSeederClassIsLoaded($package, $seederClass);

            if (! class_exists($seederClass)) {
                throw new RuntimeException("Seeder class [{$seederClass}] does not exist.");
            }

            $seeder = new $seederClass();

            if (! $seeder instanceof Seeder) {
                throw new RuntimeException("Seeder class [{$seederClass}] is invalid.");
            }

            $seeder->setContainer(app());
            $seeder->__invoke();
        }
    }

    private function publishConfig(ModulePackage $package): void
    {
        foreach ($package->configFiles as $sourcePath) {
            if (! File::exists($sourcePath)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($package->configDestination($sourcePath)));
            File::copy($sourcePath, $package->configDestination($sourcePath));
        }
    }

    private function publishAssets(ModulePackage $package): void
    {
        foreach ($package->assetPaths as $sourcePath) {
            if (! File::exists($sourcePath)) {
                continue;
            }

            $destinationPath = $package->assetDestination($sourcePath);

            if (File::isDirectory($sourcePath)) {
                File::ensureDirectoryExists($destinationPath);
                File::copyDirectory($sourcePath, $destinationPath);

                continue;
            }

            File::ensureDirectoryExists(dirname($destinationPath));
            File::copy($sourcePath, $destinationPath);
        }
    }

    private function removePublishedAssets(ModulePackage $package): void
    {
        foreach ($package->assetPaths as $sourcePath) {
            $destinationPath = $package->assetDestination($sourcePath);

            if (File::isDirectory($destinationPath)) {
                File::deleteDirectory($destinationPath);
                continue;
            }

            File::delete($destinationPath);
        }
    }

    private function removePublishedConfig(ModulePackage $package): void
    {
        foreach ($package->configFiles as $sourcePath) {
            File::delete($package->configDestination($sourcePath));
        }
    }

    private function ensureSeederClassIsLoaded(ModulePackage $package, string $seederClass): void
    {
        if (class_exists($seederClass)) {
            return;
        }

        $moduleNamespace = 'Modules\\'.Str::studly($package->key).'\\';

        if (! str_starts_with($seederClass, $moduleNamespace)) {
            return;
        }

        $relativeClass = str_replace('\\', DIRECTORY_SEPARATOR, str($seederClass)->after($moduleNamespace)->toString());
        $candidatePaths = [
            $package->basePath.DIRECTORY_SEPARATOR.$relativeClass.'.php',
            $package->basePath.DIRECTORY_SEPARATOR.str_replace('Database'.DIRECTORY_SEPARATOR.'Seeders', 'database'.DIRECTORY_SEPARATOR.'seeders', $relativeClass).'.php',
        ];

        foreach ($candidatePaths as $seederPath) {
            if (! File::exists($seederPath)) {
                continue;
            }

            require_once $seederPath;

            return;
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function callArtisan(string $command, array $parameters): void
    {
        $exitCode = Artisan::call($command, $parameters);

        if ($exitCode === 0) {
            return;
        }

        throw new RuntimeException(trim(Artisan::output()) ?: 'Module lifecycle command failed.');
    }
}
