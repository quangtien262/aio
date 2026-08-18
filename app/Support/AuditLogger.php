<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes', 'token',
        'api_token', 'access_token', 'refresh_token', 'authorization', 'credentials',
        'credential', 'secret', 'client_secret', 'private_key', 'certificate_password',
        'raw_payload', 'payload_raw', 'xml', 'pdf_base64', 'signature',
        'identity_number', 'personal_email', 'phone', 'date_of_birth', 'address',
        'base_salary', 'allowances', 'deductions', 'net_salary', 'bank_account',
        'tax_code',
    ];

    public function record(
        string $action,
        Model|string|null $target = null,
        ?array $before = null,
        ?array $after = null,
        ?Admin $actor = null,
        ?string $moduleKey = null,
    ): AuditLog {
        /** @var Request|null $request */
        $request = app()->bound('request') ? request() : null;
        $targetType = $target instanceof Model ? $target::class : (is_string($target) ? $target : null);
        $targetId = $target instanceof Model ? (string) $target->getKey() : null;

        $values = [
            'actor_admin_id' => ($actor ?? $request?->user('admin'))?->id,
            'action' => $action,
            'module_key' => $moduleKey ?: Str::before($action, '.'),
            'website_key' => app()->bound(SiteContext::class) ? app(SiteContext::class)->websiteKey() : null,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before' => $this->sanitize($before),
            'after' => $this->sanitize($after),
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 1000, ''),
            'request_id' => $request?->headers->get('X-Request-ID') ?: (string) Str::uuid(),
            'created_at' => now(),
        ];

        return DB::transaction(function () use ($values): AuditLog {
            $head = DB::table('audit_log_chain_heads')
                ->where('chain_key', 'default')
                ->lockForUpdate()
                ->first();

            if ($head === null) {
                DB::table('audit_log_chain_heads')->insertOrIgnore([
                    'chain_key' => 'default',
                    'last_sequence' => 0,
                    'last_hash' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $head = DB::table('audit_log_chain_heads')
                    ->where('chain_key', 'default')
                    ->lockForUpdate()
                    ->first();
            }

            $sequence = (int) $head->last_sequence + 1;
            $previousHash = $head->last_hash;
            $hashPayload = $this->canonicalize([
                'sequence' => $sequence,
                'previous_hash' => $previousHash,
                ...$values,
                'created_at' => $values['created_at']->format('Y-m-d H:i:s'),
            ]);
            $entryHash = hash('sha256', json_encode(
                $hashPayload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));

            $log = AuditLog::query()->create([
                ...$values,
                'sequence' => $sequence,
                'previous_hash' => $previousHash,
                'entry_hash' => $entryHash,
            ]);

            DB::table('audit_log_chain_heads')->where('chain_key', 'default')->update([
                'last_sequence' => $sequence,
                'last_hash' => $entryHash,
                'updated_at' => now(),
            ]);

            return $log;
        }, 3);
    }

    private function sanitize(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return collect($payload)->mapWithKeys(function (mixed $value, string|int $key): array {
            $normalizedKey = str((string) $key)->lower()->snake()->toString();

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                return [];
            }

            return [$key => is_array($value) ? $this->sanitize($value) : $value];
        })->all();
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
}
