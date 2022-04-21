<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $teams = ["Buffalo Bills","Miami Dolphins","New England Patriots","New York Jets","Baltimore Ravens","Cincinnati Bengals","Cleveland Browns","Pittsburgh Steelers","Denver Broncos","Kansas City Chiefs","Los Angeles Chargers","Las Vegas Raiders","Houston Texans","Indianapolis Colts","Jacksonville Jaguars","Tennessee Titans","Dallas Cowboys","New York Giants","Philadelphia Eagles","Washington","Chicago Bears","Detroit Lions","Green Bay Packers","Minnesota Vikings","Arizona Cardinals","Los Angeles Rams","San Francisco 49ers","Seattle Seahawks","Atlanta Falcons","Carolina Panthers","New Orleans Saints","Tampa Bay Buccaneers"];

        foreach ($teams as $team => $name) {
            Team::create([
                'name' => $name,
                'logo' => 'team_'.str_pad($team + 1, 2, "0", STR_PAD_LEFT).'.png',
            ]);
        }
    }
}
