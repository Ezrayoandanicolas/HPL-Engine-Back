<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $casts = [
        'sosmed_links' => 'array',
    ];

    public function User()
    {
        return $this->belongsTo(User::class);
    }
}
