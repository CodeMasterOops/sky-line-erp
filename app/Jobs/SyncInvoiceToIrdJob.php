<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use App\Services\Nepal\IrdApiService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncInvoiceToIrdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60; // seconds between retries

    public function __construct(
        private Invoice $invoice,
    ) {}

    public function handle(IrdApiService $irdApi): void
    {
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
            $this->invoice->update([
                'ird_sync_status' => 'failed',
                'ird_error' => $result['error'],
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff * $this->attempts());
            }
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
