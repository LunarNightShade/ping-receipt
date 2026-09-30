<?php

namespace App\Jobs;

use App\Models\Receipt;
use App\Services\ReceiptPrinter;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PrintReceipt
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Receipt $receipt) {}

    /**
     * Print the receipt and mark it as printed.
     *
     * Failures are caught and logged rather than thrown: this job is dispatched
     * with ->afterResponse(), so the visitor has already been sent their reply
     * and there is nothing to surface an error to. The receipt is left with
     * has_printed = false so it can be recovered with `receipts:reprint`.
     */
    public function handle(ReceiptPrinter $printer): void
    {
        try {
            $printer->print($this->receipt);

            // has_printed is intentionally not mass-assignable, so set it directly.
            $this->receipt->has_printed = true;
            $this->receipt->save();
        } catch (\Throwable $e) {
            Log::error('Failed to print receipt.', [
                'receipt_id' => $this->receipt->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
