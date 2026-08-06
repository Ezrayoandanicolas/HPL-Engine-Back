<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeamlessTransaction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'bet_money' => 'float',
        'win_money' => 'float',
        'balance_before' => 'float',
        'balance_after' => 'float',
    ];
}
