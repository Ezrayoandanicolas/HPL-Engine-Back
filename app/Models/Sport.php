<?php

namespace App\Models;
use App\Http\API\Exa;
use App\Http\API\fiver;
use App\Models\Game;
use App\Models\Sport;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class Sport extends Model
{
    protected $guarded = [];
}