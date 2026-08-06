<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('fiver_games')->truncate();
        DB::table('casino')->truncate();
        DB::table('sports')->truncate();

        $slots = [
            ['PGS01', 'Gates of Olympus', 'PRAGMATIC', 'slot'],
            ['PGS02', 'Sweet Bonanza', 'PRAGMATIC', 'slot'],
            ['PGS03', 'Starlight Princess', 'PRAGMATIC', 'slot'],
            ['PGS04', 'Wolf Gold', 'PRAGMATIC', 'slot'],
            ['PG01', 'Mahjong Ways', 'PGSOFT', 'slot'],
            ['PG02', 'Wild Bandito', 'PGSOFT', 'slot'],
            ['PG03', 'Lucky Neko', 'PGSOFT', 'slot'],
            ['HB01', 'Hot Hot Fruit', 'HABANERO', 'slot'],
            ['HB02', 'Candy Tower', 'HABANERO', 'slot'],
            ['JK01', 'Joker Madness', 'JOKERGAMING', 'slot'],
            ['SG01', 'Luxury Rome', 'SPADEGAMING', 'slot'],
            ['MG01', 'Mega Moolah', 'MP', 'slot'],
            ['CQ01', 'Lucky Dragon', 'CQ9', 'slot'],
            ['EV01', 'Wild Circus', 'EVOPLAY', 'slot'],
            ['BO01', 'Fire Hot 100', 'BOOONGO', 'slot'],
            ['AD01', 'Adventure Palace', 'AD', 'slot'],
            ['DT01', 'Dream Catcher', 'DREAMTECH', 'slot'],
            ['FS01', 'Fast Spin', 'FASTSPIN', 'slot'],
            ['PS01', 'Play Star', 'PS', 'slot'],
            ['HS01', 'Hacksaw Fury', 'HACKSAW', 'slot'],
            ['PN01', 'Book of Dead', 'PLAYNGO', 'slot'],
            ['TT01', 'Top Trend', 'TOPTREND', 'slot'],
        ];

        foreach ($slots as $s) {
            DB::table('fiver_games')->insert([
                'game_code' => $s[0],
                'game_name' => $s[1],
                'game_provider' => $s[2],
                'game_category' => $s[3],
                'status' => 1,
            ]);
        }

        $casinos = [
            ['EVO001', 'evolution', 'Evolution Gaming', 'Lightning Roulette', 'casino'],
            ['EVO002', 'evolution', 'Evolution Gaming', 'Crazy Time', 'casino'],
            ['EVO003', 'evolution', 'Evolution Gaming', 'Monopoly Live', 'casino'],
            ['PP001', 'pragmaticplay', 'Pragmatic Play Live', 'Mega Wheel', 'casino'],
            ['PP002', 'pragmaticplay', 'Pragmatic Play Live', 'Sweet Bonanza CandyLand', 'casino'],
            ['SE001', 'sesame', 'Sesame', 'Baccarat', 'casino'],
            ['AE001', 'ae', 'AE Casino', 'Sexy Baccarat', 'casino'],
        ];

        foreach ($casinos as $c) {
            DB::table('casino')->insert([
                'game_uid' => $c[0],
                'provider_code' => $c[1],
                'provider_name' => $c[2],
                'game_name' => $c[3],
                'game_type' => $c[4],
                'status' => 'active',
                'rtp' => 96.00,
            ]);
        }

        $sports = [
            ['SP001', 'saba', 'Saba Sports', 'Football League', 'sports'],
            ['SP002', 'saba', 'Saba Sports', 'Basketball League', 'sports'],
            ['SP003', 'ug', 'UG Sports', 'Football Asia', 'sports'],
            ['SP004', 'cmd', 'CMD Sports', 'Virtual Football', 'sports'],
        ];

        foreach ($sports as $sp) {
            DB::table('sports')->insert([
                'game_uid' => $sp[0],
                'provider_code' => $sp[1],
                'provider_name' => $sp[2],
                'game_name' => $sp[3],
                'game_type' => $sp[4],
                'status' => 1,
                'rtp' => 95.00,
            ]);
        }

        $this->command->info('Game seeder selesai: ' . count($slots) . ' slot, ' . count($casinos) . ' casino, ' . count($sports) . ' sports.');
    }
}
