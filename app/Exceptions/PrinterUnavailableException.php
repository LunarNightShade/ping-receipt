<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The receipt printer could not be reached (powered off, out of range, network
 * trouble). This is an expected, temporary condition: the print job is retried
 * automatically, and each attempt is already summarised by one log line in
 * PrintReceipt. It is therefore excluded from Laravel's exception reporting
 * (see bootstrap/app.php) so it doesn't also write a stack trace every time.
 */
class PrinterUnavailableException extends RuntimeException {}
