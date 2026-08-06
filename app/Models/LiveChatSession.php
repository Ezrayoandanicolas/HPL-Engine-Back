<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveChatSession extends Model
{
    use HasFactory;

    protected $table = 'livechat_sessions';

    protected $fillable = [
        'session_token',
        'user_id',
        'name',
        'email',
        'status',
        'rating',
        'assigned_to',
        'is_offline',
        'last_activity',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(LiveChatMessage::class, 'session_id');
    }
}
