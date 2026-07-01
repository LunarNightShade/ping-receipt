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
}
