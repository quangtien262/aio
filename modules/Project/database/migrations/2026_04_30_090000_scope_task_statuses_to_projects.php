<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pro__task_statuses', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('id');
            $table->index('project_id', 'pro__task_statuses_project_id_idx');
            $table->foreign('project_id', 'pro__task_statuses_project_id_foreign')->references('id')->on('pro__projects')->cascadeOnDelete();
            $table->index(['project_id', 'is_active', 'sort_order'], 'pro__task_statuses_project_sort_idx');
        });

        DB::transaction(function (): void {
            $templates = DB::table('pro__task_statuses')->whereNull('project_id')->orderBy('sort_order')->get();
            $projects = DB::table('pro__projects')->select('id')->orderBy('id')->get();

            foreach ($projects as $project) {
                $hasStatuses = DB::table('pro__task_statuses')->where('project_id', $project->id)->exists();

                if ($hasStatuses) {
                    continue;
                }

                $mapping = [];

                foreach ($templates as $template) {
                    $newId = DB::table('pro__task_statuses')->insertGetId([
                        'project_id' => $project->id,
                        'name' => $template->name,
                        'color' => $template->color,
                        'sort_order' => $template->sort_order,
                        'is_done' => $template->is_done,
                        'is_active' => $template->is_active,
                        'created_at' => $template->created_at ?? now(),
                        'updated_at' => now(),
                    ]);

                    $mapping[$template->id] = $newId;
                }

                DB::table('pro__tasks')->where('project_id', $project->id)->get(['id', 'task_status_id'])->each(function ($task) use ($mapping): void {
                    if (! isset($mapping[$task->task_status_id])) {
                        return;
                    }

                    DB::table('pro__tasks')->where('id', $task->id)->update([
                        'task_status_id' => $mapping[$task->task_status_id],
                    ]);
                });
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $templates = DB::table('pro__task_statuses')->whereNull('project_id')->get()->keyBy('name');

            DB::table('pro__task_statuses')->whereNotNull('project_id')->orderBy('project_id')->orderBy('sort_order')->get()->each(function ($status) use (&$templates): void {
                $template = $templates[$status->name] ?? null;

                if (! $template) {
                    $templateId = DB::table('pro__task_statuses')->insertGetId([
                        'project_id' => null,
                        'name' => $status->name,
                        'color' => $status->color,
                        'sort_order' => $status->sort_order,
                        'is_done' => $status->is_done,
                        'is_active' => $status->is_active,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $template = (object) ['id' => $templateId, 'name' => $status->name];
                    $templates[$status->name] = $template;
                }

                DB::table('pro__tasks')->where('task_status_id', $status->id)->update([
                    'task_status_id' => $template->id,
                ]);
            });

            DB::table('pro__task_statuses')->whereNotNull('project_id')->delete();
        });

        $indexes = $this->indexes('pro__task_statuses');
        $foreignKeys = $this->foreignKeys('pro__task_statuses', 'project_id');
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::table('pro__task_statuses', function (Blueprint $table) use ($foreignKeys, $indexes, $isSqlite): void {
            if ($isSqlite) {
                $table->dropForeign(['project_id']);
            } elseif ($foreignKeys->contains('pro__task_statuses_project_id_foreign')) {
                $table->dropForeign('pro__task_statuses_project_id_foreign');
            }

            if ($indexes->contains('pro__task_statuses_project_sort_idx')) {
                $table->dropIndex('pro__task_statuses_project_sort_idx');
            }

            if ($indexes->contains('pro__task_statuses_project_id_idx')) {
                $table->dropIndex('pro__task_statuses_project_id_idx');
            }

            $table->dropColumn('project_id');
        });
    }

    private function indexes(string $table): \Illuminate\Support\Collection
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->pluck('name')
                ->unique();
        }

        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->pluck('Key_name')
            ->unique();
    }

    private function foreignKeys(string $table, string $column): \Illuminate\Support\Collection
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return collect();
        }

        return collect(DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$table}'
                AND COLUMN_NAME = '{$column}'
                AND REFERENCED_TABLE_NAME IS NOT NULL
        "))->pluck('CONSTRAINT_NAME')->unique();
    }
};
