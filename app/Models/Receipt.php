<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $fillable = [
        'transaction',
        'message',
    ];

    protected $casts = [
        'has_printed' => 'boolean',
    ];

    /**
     * Generate a random five-digit transaction number, e.g. "04821".
     */
    public static function newTransactionNumber(): string
    {
        return str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }
}
