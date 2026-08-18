<?php

namespace App\Jobs\AccountingTax;

use App\Models\AcctExport;
use App\Models\ModuleInstallation;
use App\Support\AccountingTax\AccountingArtifactStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;

class PruneExpiredAccountingExports implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(AccountingArtifactStore $artifacts): void
    {
        if (! Schema::hasTable('acct_exports') || ! ModuleInstallation::query()
            ->where('key', 'accounting-tax')->where('status', 'enabled')->exists()) {
            return;
        }

        AcctExport::query()
            ->whereNotNull('artifact_path')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($exports) use ($artifacts): void {
                foreach ($exports as $export) {
                    $artifacts->delete($export->artifact_path);
                    $export->forceFill([
                        'status' => 'expired',
                        'artifact_path' => null,
                        'last_error' => 'Artifact đã hết hạn lưu trữ; metadata và checksum được giữ lại.',
                    ])->save();
                }
            });
    }
}
