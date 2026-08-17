<?php

namespace App\Services;

use App\Http\API\fiver;
use App\Http\API\DigitalCreative;
use App\Models\Setting;

class GameProviderService
{
    public const FIVER = 'fiver';
    public const DC = 'dc';

    public function current(): string
    {
        $setting = Setting::orderBy('created_at', 'DESC')->first();

        return $setting && in_array($setting->game_provider, [self::FIVER, self::DC], true)
            ? $setting->game_provider
            : self::FIVER;
    }

    public function api(): fiver|DigitalCreative
    {
        return $this->current() === self::DC ? new DigitalCreative() : new fiver();
    }

    public function setProvider(string $provider): string
    {
        $provider = in_array($provider, [self::FIVER, self::DC], true) ? $provider : self::FIVER;

        $setting = Setting::orderBy('created_at', 'DESC')->first();
        if (!$setting) {
            $setting = Setting::create(['web' => 'NexusEngine', 'game_provider' => $provider]);
        } else {
            $setting->update(['game_provider' => $provider]);
        }

        return $provider;
    }

    public function label(string $provider): string
    {
        return $provider === self::DC ? 'DigitalCreative' : 'Fiver';
    }
}