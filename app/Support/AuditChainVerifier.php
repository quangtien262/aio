<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class AuditChainVerifier
{
    /** @return array{valid:bool,count:int,last_sequence:int,last_hash:?string,error:?string} */
    public function verify(): array
    {
        $expectedSequence = 1;
        $previousHash = null;
        $count = 0;

        foreach (AuditLog::query()->orderBy('sequence')->cursor() as $log) {
            if ((int) $log->sequence !== $expectedSequence) {
                return $this->failure($count, $expectedSequence - 1, $previousHash, "Thiếu hoặc trùng sequence {$expectedSequence}.");
            }

            if (($log->previous_hash ?: null) !== $previousHash) {
                return $this->failure($count, $expectedSequence - 1, $previousHash, "previous_hash không khớp tại sequence {$expectedSequence}.");
            }

            $payload = $this->canonicalize([
                'sequence' => $expectedSequence,
                'previous_hash' => $previousHash,
                'actor_admin_id' => $log->actor_admin_id,
                'action' => $log->action,
                'module_key' => $log->module_key,
                'website_key' => $log->website_key,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'before' => $log->before,
                'after' => $log->after,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'request_id' => $log->request_id,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            ]);
            $expectedHash = hash('sha256', json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));

            if (! is_string($log->entry_hash) || ! hash_equals($expectedHash, $log->entry_hash)) {
                return $this->failure($count, $expectedSequence - 1, $previousHash, "entry_hash không khớp tại sequence {$expectedSequence}.");
            }

            $previousHash = $log->entry_hash;
            $expectedSequence++;
            $count++;
        }

        $head = DB::table('audit_log_chain_heads')->where('chain_key', 'default')->first();
        if ($head === null || (int) $head->last_sequence !== $count || ($head->last_hash ?: null) !== $previousHash) {
            return $this->failure($count, $expectedSequence - 1, $previousHash, 'Chain head không khớp với nhật ký cuối cùng.');
        }

        return [
            'valid' => true,
            'count' => $count,
            'last_sequence' => $expectedSequence - 1,
            'last_hash' => $previousHash,
            'error' => null,
        ];
    }

    /** @return array{valid:bool,count:int,last_sequence:int,last_hash:?string,error:string} */
    private function failure(int $count, int $lastSequence, ?string $lastHash, string $error): array
    {
        return [
            'valid' => false,
            'count' => $count,
            'last_sequence' => $lastSequence,
            'last_hash' => $lastHash,
            'error' => $error,
        ];
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
