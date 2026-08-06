<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Casino extends Model
{
    use HasFactory;

    protected $table = 'casino';

    protected $fillable = [
        'game_uid',
        'provider_code',
        'provider_name',
        'game_name',
        'game_type',
        'image_url',
        'rtp',
        'volatility',
        'min_bet',
        'max_bet',
        'status',
    ];
}