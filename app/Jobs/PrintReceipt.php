<?php

namespace App\Jobs;

use App\Models\Receipt;
use App\Services\ReceiptPrinter;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PrintReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Receipt $receipt) {}

    /**
     * Seconds to wait before each retry. Once the list runs out the last value
     * is reused, so after the first few quick attempts it retries every 15
     * minutes until the retry window (see retryUntil) closes.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300, 900];
    }

    /**
     * Keep retrying until this moment. It is worked out when the job is
     * dispatched, so the window starts from when the message was sent.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours((int) config('printer.retry_hours', 6));
    }

    /**
     * Print the receipt and mark it as printed.
     *
     * If the printer can't be reached the exception is deliberately re-thrown:
     * that is what tells the queue to try again later using backoff() above.
     */
    public function handle(ReceiptPrinter $printer): void
    {
        // A retry can arrive after an earlier attempt already printed (for
        // example the printer worked but saving the flag failed). Never print
        // the same message twice.
        if ($this->receipt->has_printed) {
            return;
        }

        try {
            $printer->print($this->receipt);
        } catch (Throwable $e) {
            Log::warning('Printing failed, will retry.', [
                'receipt_id' => $this->receipt->id,
                'attempt' => $this->attempts(),
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }

        // has_printed is intentionally not mass-assignable, so set it directly.
        $this->receipt->has_printed = true;
        $this->receipt->save();

        Log::info('Printed receipt.', [
            'receipt_id' => $this->receipt->id,
            'attempts' => $this->attempts(),
        ]);
    }

    /**
     * Called once the queue has given up (the retry window closed). The
     * receipt stays has_printed = false so `receipts:reprint` can recover it.
     */
    public function failed(Throwable $e): void
    {
        Log::error('Gave up printing receipt; recover it with `php artisan receipts:reprint`.', [
            'receipt_id' => $this->receipt->id,
            'exception' => $e->getMessage(),
        ]);
    }
}
