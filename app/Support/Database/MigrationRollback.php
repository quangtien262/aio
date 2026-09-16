<?php

namespace App\Support\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class MigrationRollback
{
    public static function dropForeignKeysForColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach (Schema::getForeignKeys($tableName) as $foreign) {
            if (array_intersect($columns, $foreign['columns']) !== []) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign($foreign['name'] ?? $foreign['columns']));
            }
        }
    }

    /** Drop constraints before their columns on both MySQL and SQLite. */
    public static function dropColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $columns = array_values(array_intersect($columns, Schema::getColumnListing($tableName)));
        if ($columns === []) {
            return;
        }

        self::dropForeignKeysForColumns($tableName, $columns);

        foreach (Schema::getIndexes($tableName) as $index) {
            if (! $index['primary'] && array_intersect($columns, $index['columns']) !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($index): void {
                    $index['unique'] ? $table->dropUnique($index['name']) : $table->dropIndex($index['name']);
                });
            }
        }

        Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($columns));
    }
}
