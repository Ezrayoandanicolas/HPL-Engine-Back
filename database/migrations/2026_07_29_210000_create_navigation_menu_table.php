<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_menu', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('url', 200);
            $table->string('image', 500)->nullable();
            $table->string('category', 50);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->seed();
    }

    private function seed(): void
    {
        $items = [];

        // Hot Games
        foreach ([
            ['Pragmatic Play','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-7.webp?v=20240708-4'],
            ['Hacksaw','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-98.webp?v=20240708-4'],
            ['Habanero','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-16.webp?v=20240708-4'],
            ['MicroGaming','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-17.webp?v=20240708-4'],
            ['PG Slots','/slots/pgsoft','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-9.webp?v=20240708-4'],
            ['No Limit City','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-92.webp?v=20240708-4'],
            ['ION Casino','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-1.webp?v=20240708-4'],
            ['Jili','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-70.webp?v=20240708-4'],
        ] as $i) { $items[] = ['title'=>$i[0],'url'=>$i[1],'image'=>$i[2],'category'=>'Hot Games']; }

        // Slots
        foreach ([
            ['Pragmatic Play','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-7.webp?v=20240708-4'],
            ['PG Slots','/slots/pgsoft','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-9.webp?v=20240708-4'],
            ['Hacksaw','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-98.webp?v=20240708-4'],
            ['MicroGaming','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-17.webp?v=20240708-4'],
            ['Habanero','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-16.webp?v=20240708-4'],
            ['No Limit City','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-92.webp?v=20240708-4'],
            ['Jili','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-70.webp?v=20240708-4'],
            ['Spade Gaming','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-29.webp?v=20240708-4'],
            ['Joker','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-6.webp?v=20240708-4'],
            ['AdvantPlay','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-54.webp?v=20240708-4'],
            ['Playstar','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-65.webp?v=20240708-4'],
            ['Spinix','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-91.webp?v=20240708-4'],
            ['Crowd Play','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-73.webp?v=20240708-4'],
            ['Bigpot','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-75.webp?v=20240708-4'],
            ['VPower','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-77.webp?v=20240708-4'],
            ['Worldmatch','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-89.webp?v=20240708-4'],
            ['Fachai','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-72.webp?v=20240708-4'],
            ['Slot88','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-40.webp?v=20240708-4'],
            ['ION Slot','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-50.webp?v=20240708-4'],
            ['AMB Slot','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-61.webp?v=20240708-4'],
            ['Mario Club','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-80.webp?v=20240708-4'],
            ['Dragoonsoft','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-81.webp?v=20240708-4'],
            ['Naga Games','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-87.webp?v=20240708-4'],
            ['JDB','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-51.webp?v=20240708-4'],
            ['CQ9','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-13.webp?v=20240708-4'],
            ['Only Play','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-97.webp?v=20240708-4'],
            ['Top Trend Gaming','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-95.webp?v=20240708-4'],
            ['Netent','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-31.webp?v=20240708-4'],
            ['Big Time Gaming','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-86.webp?v=20240708-4'],
            ['Red Tiger','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-88.webp?v=20240708-4'],
            ['Skywind','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-60.webp?v=20240708-4'],
            ['Yggdrasil','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-38.webp?v=20240708-4'],
            ['Play\'n Go','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-8.webp?v=20240708-4'],
            ['Real Time Gaming','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-93.webp?v=20240708-4'],
        ] as $i) { $items[] = ['title'=>$i[0],'url'=>$i[1],'image'=>$i[2],'category'=>'Slots']; }

        // Live Casino
        foreach ([
            ['ION Casino','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-1.webp?v=20240708-4'],
            ['PP Casino','/casino','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-38.webp?v=20240708-4'],
            ['MG Live','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-32.webp?v=20240708-4'],
            ['Evo Gaming','/casino','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-67.webp?v=20240708-4'],
            ['Sexy Baccarat','/casino','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-3.webp?v=20240708-4'],
            ['Pretty Gaming','/casino','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-4.webp?v=20240708-4'],
            ['Oriental Gaming','/casino','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-54.webp?v=20240708-4'],
            ['AllBet','/casino','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-5.webp?v=20240708-4'],
            ['Opus Live Casino','/casino',''],
            ['SA Gaming','/casino','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-14.webp?v=20240708-4'],
            ['Ebet','/casino','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-15.webp?v=20240708-4'],
            ['568Win Casino','/casino',''],
        ] as $i) { $items[] = ['title'=>$i[0],'url'=>$i[1],'image'=>$i[2],'category'=>'Live Casino']; }

        // Sports (Olahraga)
        foreach ([
            ['SABA Sports','/sports','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-103.webp?v=20240708-4'],
            ['Pinnacle','/sports','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-101.webp?v=20240708-4'],
        ] as $i) { $items[] = ['title'=>$i[0],'url'=>$i[1],'image'=>$i[2],'category'=>'Sports']; }

        // Arcade
        foreach ([
            ['MicroGaming','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-17.webp?v=20240708-4'],
            ['Spinix','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-91.webp?v=20240708-4'],
            ['Spribe','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-23.webp?v=20240708-4'],
            ['Joker','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-6.webp?v=20240708-4'],
            ['Fachai','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-72.webp?v=20240708-4'],
            ['Jili','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-70.webp?v=20240708-4'],
            ['AMB Slot','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-61.webp?v=20240708-4'],
            ['Crowd Play','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-73.webp?v=20240708-4'],
            ['VPower','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-77.webp?v=20240708-4'],
            ['Worldmatch','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-89.webp?v=20240708-4'],
            ['Mario Club','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-80.webp?v=20240708-4'],
            ['Dragoonsoft','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-81.webp?v=20240708-4'],
            ['Live22','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-14.webp?v=20240708-4'],
            ['CQ9','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-13.webp?v=20240708-4'],
            ['Spade Gaming','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-29.webp?v=20240708-4'],
            ['Arcadia','/arcade',''],
            ['MM Tangkas','/arcade',''],
            ['Skywind','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-60.webp?v=20240708-4'],
            ['Playstar','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-65.webp?v=20240708-4'],
            ['AdvantPlay Mini Game','/slots','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-54.webp?v=20240708-4'],
            ['JDB','/arcade','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-3/game-code-51.webp?v=20240708-4'],
            ['Funky Games','/arcade',''],
        ] as $i) { $items[] = ['title'=>$i[0],'url'=>$i[1],'image'=>$i[2],'category'=>'Arcade']; }

        // Poker
        foreach ([
            ['Balak Play','/poker','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-102.webp?v=20240708-4'],
            ['9Gaming','/poker','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-2.webp?v=20240708-4'],
        ] as $i) { $items[] = ['title'=>$i[0],'url'=>$i[1],'image'=>$i[2],'category'=>'Poker']; }

        // Sabung Ayam
        foreach ([
            ['WS168','/cockfight',''],
            ['SV388','/cockfight','https://d33egg70nrp50s.cloudfront.net/Images/v-zelma-v2-beta/menu/desktop/home-menu-2/game-code-27.webp?v=20240708-4'],
        ] as $i) { $items[] = ['title'=>$i[0],'url'=>$i[1],'image'=>$i[2],'category'=>'Sabung Ayam']; }

        $order = 0;
        foreach ($items as $item) {
            $order++;
            DB::table('navigation_menu')->insert([
                'title' => $item['title'],
                'url' => $item['url'],
                'image' => $item['image'],
                'category' => $item['category'],
                'sort_order' => $order,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_menu');
    }
};
