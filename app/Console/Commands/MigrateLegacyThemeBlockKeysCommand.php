<?php

namespace App\Console\Commands;

use App\Models\ThemeTranslation;
use App\Support\ThemeBlockRegistry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Console\Command;

class MigrateLegacyThemeBlockKeysCommand extends Command
{
    protected $signature = 'theme-blocks:migrate-legacy
        {--theme=SER0101 : Theme key whose legacy keys should be migrated}
        {--dry-run : Show the rows that would be migrated without writing changes}';

    protected $description = 'Migrate legacy theme_metric/theme_section content overrides to theme_block.<theme>.* keys';

    public function handle(ThemeBlockRegistry $themeBlockRegistry): int
    {
        $themeKey = (string) $this->option('theme');
        $dryRun = (bool) $this->option('dry-run');
        $legacyMap = $themeBlockRegistry->legacyKeyMap($themeKey);

        if ($legacyMap === []) {
            $this->warn(sprintf('Không có mapping legacy nào được định nghĩa cho theme `%s`.', $themeKey));

            return self::SUCCESS;
        }

        $legacyKeys = array_keys($legacyMap);
        /** @var EloquentCollection<int, ThemeTranslation> $rows */
        $rows = ThemeTranslation::query()
            ->where('group', 'content')
            ->whereIn('translation_key', $legacyKeys)
            ->orderBy('theme_key')
            ->orderBy('locale')
            ->orderBy('translation_key')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Không tìm thấy override legacy nào để migrate.');

            return self::SUCCESS;
        }

        $this->table(
            ['Theme key', 'Locale', 'From', 'To'],
            $rows->map(fn (ThemeTranslation $row): array => [
                $row->theme_key,
                $row->locale,
                $row->translation_key,
                $legacyMap[$row->translation_key] ?? '',
            ])->all(),
        );

        if ($dryRun) {
            $this->info(sprintf('Dry run: %d row(s) would be migrated.', $rows->count()));

            return self::SUCCESS;
        }

        $migrated = 0;

        foreach ($rows as $row) {
            /** @var ThemeTranslation $row */
            $targetKey = $legacyMap[$row->translation_key] ?? null;

            if ($targetKey === null) {
                continue;
            }

            ThemeTranslation::query()->updateOrCreate(
                [
                    'theme_key' => $row->theme_key,
                    'locale' => $row->locale,
                    'group' => $row->group,
                    'translation_key' => $targetKey,
                ],
                [
                    'value' => $row->value,
                ],
            );

            $row->delete();
            $migrated += 1;
        }

        $this->info(sprintf('Đã migrate %d row(s) sang namespace theme_block.', $migrated));

        return self::SUCCESS;
    }
}
