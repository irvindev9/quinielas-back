<?php

namespace Database\Seeders;

use App\Models\Season;
use Illuminate\Database\Seeder;

class SeasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        for($i = 0; $i <= 5; $i++) {
            $actual_year = date('Y');

            Season::create([
                'name' => 'Temporada ' . ($actual_year + $i),
                'is_active' => $i === 0 ? 1 : 0,
                'is_register_open' => $i === 0 ? 1 : 0,
            ]);
        }
    }
}
