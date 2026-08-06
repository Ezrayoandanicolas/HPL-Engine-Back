<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrisAccount extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'config' => 'array',
        'enabled' => 'boolean',
        'last_used_at' => 'datetime',
        'use_count' => 'integer',
    ];

    public function getConfigValue(string $key, $default = null)
    {
        $config = is_array($this->config) ? $this->config : [];
        return $config[$key] ?? $default;
    }
}
