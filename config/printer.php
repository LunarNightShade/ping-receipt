<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Receipt Printer Connection
    |--------------------------------------------------------------------------
    |
    | Connection details for the ESC/POS receipt printer. These are read from
    | the environment so you can change printers or networks without editing
    | any code. The defaults match the printer this project was built for.
    |
    | "timeout" is how many seconds to wait when connecting before giving up
    | (the print job is then retried, see "retry_hours" below).
    |
    */

    'host' => env('PRINTER_HOST', '192.168.1.217'),

    'port' => (int) env('PRINTER_PORT', 9100),

    'timeout' => (int) env('PRINTER_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Retrying Failed Prints
    |--------------------------------------------------------------------------
    |
    | If the printer can't be reached (powered off, out of range, network
    | hiccup) the queued print job is retried automatically with a growing
    | delay between attempts. "retry_hours" is how long, from the moment the
    | message was sent, it keeps trying. After that the job is recorded in the
    | failed_jobs table and can be recovered with `receipts:reprint`.
    |
    */

    'retry_hours' => (int) env('PRINTER_RETRY_HOURS', 6),

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | "width" is the number of characters that fit across one printed line
    | (48 for most 80mm printers). "recipient" is the sub-heading printed
    | under the PING title.
    |
    */

    'width' => (int) env('PRINTER_WIDTH', 48),

    'recipient' => env('PRINTER_RECIPIENT', 'MESSAGE FOR LUNAR AURORA'),

];
