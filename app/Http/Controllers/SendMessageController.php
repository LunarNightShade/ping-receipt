<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Receipt;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;

class SendMessageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // Convert smart quotes to regular apostrophes
        $message = str_replace(['"', '“', '”', "'", '‘', '’'], ['"', '"', '"', "'", "'", "'"], $request->message);
        $request->merge(['message' => $message]);

        $request->validate([
            'message' => 'required|max:1024|regex:/^[\x00-\x7F\']*$/',
            'transaction' => 'required|max:5|min:5'
        ], [
            'message.required' => 'You have to write something!',
            'message.max' => 'How did you manage to write something that long?',
            'message.regex' => 'Sorry, basic ascii characters only (bummer, I know).',
            'transaction.required' => 'How did you remove the transaction? Put it back!',
            'transaction.max' => 'What are you doing to the transaction?',
            'transaction.min' => 'What are you doing to the transaction?',
        ]);

	    Receipt::create($request->only(['transaction', 'message']));

        $connector = new NetworkPrintConnector("192.168.1.217",9100);
        $printer = new Printer($connector);

        // let me know something's coming
        $printer->feed(1);
        sleep(1);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(2, 2);
        $printer->setEmphasis(true);
        $printer->text("PING");
        $printer->feed(2);
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        $printer->text('MESSAGE FOR LUNAR AURORA');
        $printer->feed(1);

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text(str_repeat('-', 48));

        $printer->feed(2);
        $printer->text('TIMESTAMP:' . str_repeat(' ', 21) . now()->format('m/d/y h:i A'));
        $printer->feed(1);
        $printer->text('TRANSACTION #:' . str_repeat(' ', 29) . $request->transaction);
        $printer->feed(2);

        $printer->text($request->message);
        $printer->feed(2);

        $printer->cut();
        $printer->close();

        $request->session()->flash('success', 'Your message was sent successfully, woohoo!');

        return redirect()->back();
    }
}
