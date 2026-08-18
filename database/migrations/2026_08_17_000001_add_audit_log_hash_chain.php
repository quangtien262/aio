<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('sequence')->nullable()->unique()->after('id');
            $table->char('previous_hash', 64)->nullable()->after('request_id');
            $table->char('entry_hash', 64)->nullable()->unique()->after('previous_hash');
        });

        Schema::create('audit_log_chain_heads', function (Blueprint $table): void {
            $table->string('chain_key', 50)->primary();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->char('last_hash', 64)->nullable();
            $table->timestamps();
        });

        $previousHash = null;
        $sequence = 0;

        DB::table('audit_logs')->orderBy('id')->chunkById(500, function ($logs) use (&$previousHash, &$sequence): void {
            foreach ($logs as $log) {
                $sequence++;
                $payload = [
                    'sequence' => $sequence,
                    'previous_hash' => $previousHash,
                    'actor_admin_id' => $log->actor_admin_id,
                    'action' => $log->action,
                    'module_key' => $log->module_key,
                    'website_key' => $log->website_key,
                    'target_type' => $log->target_type,
                    'target_id' => $log->target_id,
                    'before' => $this->decodeJson($log->before),
                    'after' => $this->decodeJson($log->after),
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'request_id' => $log->request_id,
                    'created_at' => (string) $log->created_at,
                ];
                $entryHash = hash('sha256', json_encode(
                    $this->canonicalize($payload),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ));

                DB::table('audit_logs')->where('id', $log->id)->update([
                    'sequence' => $sequence,
                    'previous_hash' => $previousHash,
                    'entry_hash' => $entryHash,
                ]);
                $previousHash = $entryHash;
            }
        });

        DB::table('audit_log_chain_heads')->insert([
            'chain_key' => 'default',
            'last_sequence' => $sequence,
            'last_hash' => $previousHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_chain_heads');

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropColumn(['sequence', 'previous_hash', 'entry_hash']);
        });
    }

    private function decodeJson(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return json_decode($value, true) ?? $value;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $child) {
            $value[$key] = $this->canonicalize($child);
        }

        return $value;
    }
};
