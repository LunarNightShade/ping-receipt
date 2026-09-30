<?php

namespace App\Console\Commands;

use App\Models\Receipt;
use App\Services\ReceiptPrinter;
use Illuminate\Console\Command;

class ReprintReceipts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'receipts:reprint {--all : Reprint every stored receipt, not just the ones that failed}';

    /**
     * @var string
     */
    protected $description = 'Reprint receipts (by default, only those that have not printed yet)';

    public function handle(ReceiptPrinter $printer): int
    {
        $receipts = Receipt::query()
            ->unless($this->option('all'), fn ($query) => $query->where('has_printed', false))
            ->orderBy('id')
            ->get();

        if ($receipts->isEmpty()) {
            $this->info('Nothing to reprint.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($receipts as $receipt) {
            try {
                $printer->print($receipt);
                $receipt->has_printed = true;
                $receipt->save();
                $this->info("Printed receipt #{$receipt->id} ({$receipt->transaction}).");
            } catch (\Throwable $e) {
                $failures++;
                $this->error("Receipt #{$receipt->id} failed: {$e->getMessage()}");
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
