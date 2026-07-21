<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes', 'token',
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

        return AuditLog::query()->create([
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
        ]);
    }

    private function sanitize(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return collect($payload)->mapWithKeys(function (mixed $value, string|int $key): array {
            if (in_array((string) $key, self::SENSITIVE_KEYS, true)) {
                return [];
            }

            return [$key => is_array($value) ? $this->sanitize($value) : $value];
        })->all();
    }
}
