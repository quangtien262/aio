<?php

namespace App\Console\Commands;

use App\Models\ContentTranslation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneOrphanedContentTranslationsCommand extends Command
{
    protected $signature = 'localization:prune-orphans
        {--website=website-main : Website key to inspect}
        {--force : Permanently delete the orphan translations}';

    protected $description = 'Find or delete content translations whose master business record no longer exists.';

    public function handle(): int
    {
        $websiteKey = trim((string) $this->option('website')) ?: 'website-main';
        $force = (bool) $this->option('force');
        $rows = [];
        $total = 0;

        DB::transaction(function () use ($websiteKey, $force, &$rows, &$total): void {
            foreach ((array) config('localized-content.resources', []) as $resourceType => $definition) {
                $modelClass = $definition['model'] ?? null;

                if (! is_string($modelClass) || ! class_exists($modelClass)) {
                    continue;
                }

                /** @var Model $model */
                $model = new $modelClass;

                if (! Schema::hasTable($model->getTable())) {
                    continue;
                }

                $masterQuery = $modelClass::query()->withoutGlobalScopes();

                if (Schema::hasColumn($model->getTable(), 'website_key')) {
                    $masterQuery->where('website_key', $websiteKey);
                }

                $masterIds = $masterQuery->pluck($model->getKeyName())
                    ->map(fn (mixed $id): string => (string) $id)
                    ->all();
                $orphanQuery = ContentTranslation::query()
                    ->withoutGlobalScopes()
                    ->where('website_key', $websiteKey)
                    ->where('resource_type', $resourceType)
                    ->whereNotIn('resource_id', $masterIds);
                $count = (clone $orphanQuery)->count();

                if ($count === 0) {
                    continue;
                }

                $rows[] = [$resourceType, $count, $force ? 'deleted' : 'dry-run'];
                $total += $count;

                if ($force) {
                    $orphanQuery->delete();
                }
            }
        });

        $this->table(['Resource', 'Rows', 'Action'], $rows);
        $this->line(sprintf(
            '%d orphan translation row(s) %s for %s.',
            $total,
            $force ? 'deleted' : 'found',
            $websiteKey,
        ));

        if (! $force && $total > 0) {
            $this->warn('Dry-run only. Re-run with --force after reviewing the exact scope.');
        }

        return self::SUCCESS;
    }
}
