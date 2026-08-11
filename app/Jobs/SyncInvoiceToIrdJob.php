<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use App\Services\TenantService;
use Illuminate\Support\Facades\Log;
use App\Services\Nepal\IrdApiService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\Middleware\SkipsDisabledModule;

class SyncInvoiceToIrdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60; // seconds between retries

    public function __construct(
        private Invoice $invoice,
    ) {}

    /**
     * Syncing pushes an invoice to a government system, so it is the one piece
     * of module work that must never outlive the module. The legacy
     * `ird_ebs_enabled` company flag is not enough on its own: a tenant whose
     * Nepal Compliance module is off would otherwise keep filing.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new SkipsDisabledModule('nepal-compliance', (int) $this->invoice->company_id)];
    }

    public function handle(IrdApiService $irdApi): void
    {
        // Queue workers run without HTTP tenant context; establish it from the
        // invoice so tenant-scoped queries inside the sync resolve correctly.
        // Company context (not branch): the job operates on a single invoice by
        // reference and must not branch-filter its eager-loaded relations.
        TenantService::setCompanyId($this->invoice->company_id);

        try {
            $this->invoice->refresh();

            if ($this->invoice->ird_sync_status === 'synced') {
                return;
            }

            $result = $irdApi->syncInvoice($this->invoice);

            if ($result['skipped'] ?? false) {
                $this->invoice->update(['ird_sync_status' => 'skipped']);

                return;
            }

            if ($result['success']) {
                $this->invoice->update([
                    'ird_sync_status' => 'synced',
                    'ird_internal_id' => $result['ird_internal_id'],
                    'ird_qr_data' => $result['ird_qr_data'],
                    'ird_synced_at' => now(),
                    'ird_error' => null,
                ]);

                Log::info('IRD EBS sync successful', [
                    'invoice_id' => $this->invoice->id,
                    'invoice_no' => $this->invoice->invoice_no,
                    'ird_internal_id' => $result['ird_internal_id'],
                ]);
            } else {
                $isLastAttempt = $this->attempts() >= $this->tries;

                $this->invoice->update([
                    'ird_sync_status' => $isLastAttempt ? 'failed' : 'retrying',
                    'ird_error' => $result['error'],
                ]);

                if (! $isLastAttempt) {
                    $this->release($this->backoff * $this->attempts());
                }
            }
        } finally {
            TenantService::reset();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->invoice->update([
            'ird_sync_status' => 'failed',
            'ird_error' => $exception->getMessage(),
        ]);

        Log::error('IRD EBS sync job failed permanently', [
            'invoice_id' => $this->invoice->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
