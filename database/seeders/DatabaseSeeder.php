<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Setting;
use App\Models\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\GameSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::create([
            'web'          => 'NexusEngine',
            'logo'         => null,
            'icon'         => null,
            'seo'          => 'NexusEngine - Gaming Platform',
            'whatsapp'     => null,
            'telegram'     => null,
            'livechat'     => null,
            'maintenance'  => 0,
        ]);

        User::create([
            'name'     => 'Admin',
            'username' => 'admin',
            'email'    => 'admin@admin.com',
            'password' => Hash::make('admin123'),
            'role'     => 'admin',
        ]);

        foreach (['Pending', 'Success', 'Failed'] as $i => $name) {
            Status::create([
                'name'  => $name,
                'color' => ['#ffc107', '#28a745', '#dc3545'][$i],
            ]);
        }

        $this->call(GameSeeder::class);
    }
}
