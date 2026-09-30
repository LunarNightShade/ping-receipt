<?php

namespace App\Services;

use App\Models\Receipt;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;

class ReceiptPrinter
{
    /**
     * Connect to the printer and print a single receipt.
     *
     * Throws if the printer is unreachable; callers decide how to handle that.
     */
    public function print(Receipt $receipt): void
    {
        $connector = new NetworkPrintConnector(
            (string) config('printer.host'),
            (int) config('printer.port'),
        );

        $printer = new Printer($connector);

        try {
            $this->render($printer, $receipt);
        } finally {
            // Always release the socket, even if rendering throws part-way.
            $printer->close();
        }
    }

    /**
     * Write the receipt layout to the printer.
     */
    private function render(Printer $printer, Receipt $receipt): void
    {
        $width = (int) config('printer.width', 48);

        // Feed a blank line then pause briefly so it's noticeable that
        // something is about to print (the original author's touch).
        $printer->feed(1);
        sleep(1);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(2, 2);
        $printer->setEmphasis(true);
        $printer->text('PING');
        $printer->feed(2);

        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        $printer->text((string) config('printer.recipient', 'MESSAGE'));
        $printer->feed(1);

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text(str_repeat('-', $width));
        $printer->feed(2);

        $printer->text($this->columns('TIMESTAMP:', $receipt->created_at->format('m/d/y h:i A'), $width));
        $printer->feed(1);
        $printer->text($this->columns('TRANSACTION #:', $receipt->transaction, $width));
        $printer->feed(2);

        $printer->text($receipt->message);
        $printer->feed(2);

        $printer->cut();
    }

    /**
     * Left-align a label and right-align a value across a fixed-width line.
     */
    private function columns(string $label, string $value, int $width): string
    {
        $gap = max(1, $width - strlen($label) - strlen($value));

        return $label.str_repeat(' ', $gap).$value;
    }
}
