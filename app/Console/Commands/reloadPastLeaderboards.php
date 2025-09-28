<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\LeaderBoardHelper;
use App\Models\Week;
use App\Models\Season;

class reloadPastLeaderboards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reload-past-leaderboards {week_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '[week_id] Actualiza el leaderboard para una semana pasada';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $week_id = $this->argument('week_id');
        $active_season = Season::where('is_active', 1)->first();
        $week = Week::where('season_id', $active_season->id)->find($week_id);

        if (!$week) {
            $this->error('No se encontro la semana en la temporada activa');
            return;
        }

        $this->info('Actualizando leaderboard para la semana ' . $week->name);

        LeaderBoardHelper::getLeaderBoard($week->end_date);

        $this->info('Leaderboard actualizado para la semana ' . $week->name);
    }
}
