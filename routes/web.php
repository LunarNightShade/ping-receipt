<?php

use App\Http\Controllers\SendMessageController;
use App\Models\Receipt;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Remember the number shown on the page so the receipt that prints carries
    // the same one. It's kept in the session rather than a hidden form field
    // so visitors can't pick their own.
    $transaction = Receipt::newTransactionNumber();
    session(['transaction' => $transaction]);

    return view('app', [
        'timestamp' => now()->format('m/d/y h:i A'),
        'transaction' => $transaction,
    ]);
});

Route::post('/send-message', SendMessageController::class)
    ->name('send-message')
    ->middleware('throttle:10,1');
