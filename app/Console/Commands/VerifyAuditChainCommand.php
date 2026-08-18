<?php

namespace App\Console\Commands;

use App\Support\AuditChainVerifier;
use Illuminate\Console\Command;

class VerifyAuditChainCommand extends Command
{
    protected $signature = 'audit:verify-chain {--json : Xuất kết quả JSON}';

    protected $description = 'Kiểm tra tính toàn vẹn của chuỗi hash audit log';

    public function handle(AuditChainVerifier $verifier): int
    {
        $result = $verifier->verify();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } elseif ($result['valid']) {
            $this->info("Audit chain hợp lệ: {$result['count']} bản ghi.");
        } else {
            $this->error('Audit chain không hợp lệ: '.$result['error']);
        }

        return $result['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
