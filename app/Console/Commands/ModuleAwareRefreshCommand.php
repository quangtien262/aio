<?php

namespace App\Console\Commands;

use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Events\DatabaseRefreshed;
use Illuminate\Database\Migrations\Migrator;

class ModuleAwareRefreshCommand extends RefreshCommand
{
    public function handle()
    {
        if ($this->isProhibited() || ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $step = (int) $this->option('step');
        $options = array_filter([
            '--database' => $this->option('database'),
            '--path' => $this->option('path'),
            '--realpath' => $this->option('realpath'),
            '--force' => true,
        ]);
        $migrateOptions = $options;

        // A partial refresh must re-run the exact module migrations it rolled
        // back, without installing every package on a normal core migration.
        if ($step > 0 && ! $this->option('path')) {
            $migrator = $this->laravel->make(Migrator::class);
            $files = $migrator->usingConnection($this->option('database'), function () use ($migrator, $step): array {
                if (! $migrator->repositoryExists()) {
                    return [];
                }

                $paths = array_merge(
                    [$this->laravel->databasePath('migrations')],
                    $migrator->paths(),
                    glob(base_path('modules/*/database/migrations'), GLOB_ONLYDIR) ?: [],
                );
                $names = array_column($migrator->getRepository()->getMigrations($step), 'migration');

                return array_values(array_intersect_key($migrator->getMigrationFiles($paths), array_flip($names)));
            });
            if ($files !== []) {
                $migrateOptions['--path'] = $files;
                $migrateOptions['--realpath'] = true;
            }
        }

        $result = $step > 0
            ? $this->call('migrate:rollback', $options + ['--step' => $step])
            : $this->call('migrate:reset', $options);
        if ($result !== self::SUCCESS) {
            return $result;
        }

        $result = $this->call('migrate', $migrateOptions);
        if ($result !== self::SUCCESS) {
            return $result;
        }

        $this->laravel['events']->dispatch(new DatabaseRefreshed($this->option('database'), $this->needsSeeding()));

        return $this->needsSeeding()
            ? $this->call('db:seed', array_filter([
                '--database' => $this->option('database'),
                '--class' => $this->option('seeder') ?: 'Database\\Seeders\\DatabaseSeeder',
                '--force' => true,
            ]))
            : self::SUCCESS;
    }
}
