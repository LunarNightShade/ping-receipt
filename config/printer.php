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
    */

    'host' => env('PRINTER_HOST', '192.168.1.217'),

    'port' => (int) env('PRINTER_PORT', 9100),

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
