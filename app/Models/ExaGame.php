<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaGame extends Model
{
    protected $table = 'exagames';
    protected $fillable = ['provider_code', 'game_uid', 'game_name', 'logo_url', 'is_active'];
}