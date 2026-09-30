<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Jobs\PrintReceipt;
use App\Models\Receipt;

class SendMessageController extends Controller
{
    /**
     * Store an incoming message and queue it for printing.
     */
    public function __invoke(SendMessageRequest $request)
    {
        $receipt = Receipt::create([
            'transaction' => str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'message' => $request->validated('message'),
        ]);

        // Print after the HTTP response is sent, so a slow or offline printer
        // never makes the visitor wait (or see an error). If printing fails the
        // receipt stays unprinted and can be recovered with `receipts:reprint`.
        PrintReceipt::dispatch($receipt)->afterResponse();

        return redirect()
            ->back()
            ->with('success', 'Your message was sent successfully, woohoo!');
    }
}
