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
            // The number the visitor saw on the page (see routes/web.php). If
            // the session has none, e.g. it expired, generate a fresh one.
            'transaction' => $request->session()->pull('transaction') ?? Receipt::newTransactionNumber(),
            'message' => $request->validated('message'),
        ]);

        // Hand the print to the queue. A background worker prints it, retrying
        // automatically if the printer is off or unreachable, so the visitor
        // never waits on (or sees an error from) the printer. See PrintReceipt.
        PrintReceipt::dispatch($receipt);

        return redirect()
            ->back()
            ->with('success', 'Your message was sent successfully, woohoo!');
    }
}
