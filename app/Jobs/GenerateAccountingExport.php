<?php

namespace App\Jobs;

use App\Models\AcctExport;
use App\Support\AccountingTax\AccountingExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateAccountingExport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $exportId)
    {
        $this->onQueue('accounting');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->exportId;
    }

    public function handle(AccountingExportService $exports): void
    {
        $export = AcctExport::query()->find($this->exportId);

        if (! $export) {
            return;
        }

        $exports->generate($export);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            app(AccountingExportService::class)->markFailed($this->exportId, $exception);
        }
    }
}
