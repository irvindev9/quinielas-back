<?php

namespace App\Utils;

use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Season;
use App\Models\Week;
use App\Models\Play;
use App\Models\Leaderboard;

class LeaderBoardHelper
{
    public static function getLeaderBoard($end_date = null)
    {
        // unlimited timeout 
        ini_set('max_execution_time', 0);

        if ($end_date) {
            Redis::del('leaderboard');
        }

        if ($leaderBoard = Redis::get('leaderboard')) {
            $leaderBoard = json_decode($leaderBoard);
            // convert to array
            $leaderBoard = array_map(function($user){
                return (array) $user;
            }, $leaderBoard);
            $leaderBoard = self::addLastPosition($leaderBoard);
            return $leaderBoard;
        }

        $Users = User::with('results')->where('is_hide', 0)->get();

        $active_season = Season::where('is_active', 1)->first();
        $weeks_id = Week::where('season_id', $active_season->id);
        if ($end_date) {
            $weeks_id = $weeks_id->where('end_date', '<=', $end_date);
        }
        $weeks_id = $weeks_id->pluck('id')->toArray();
        $matches = Play::whereIn('week_id', $weeks_id)->get();

        $leaderBoard = [];

        foreach($Users as $user){
            $leaderBoard[$user->id]['points'] = 0;
            $leaderBoard[$user->id]['user_id'] = $user->id;
            $leaderBoard[$user->id]['name'] = $user->name;
            $leaderBoard[$user->id]['img'] = $user->img;
            $leaderBoard[$user->id]['team_id'] = $user->team_id;
            foreach($user->results as $result){
                $match = $matches->where('id', $result->match_id)->first();
                // Si hay un ganador y el usuario gano se suma 1 punto
                if(isset($match->winner_id)){
                    $leaderBoard[$user->id]['points'] += ($result->team_id == $match->winner_id) ? 1 : 0;
                }
            }
        }

        $leaderBoard = collect($leaderBoard)->sortByDesc('points')->values()->all();

        $position = 1;

        foreach($leaderBoard as $key => $user){
            if($key == 0){
                $leaderBoard[$key]['position'] = $position;
            }else{
                if($leaderBoard[$key]['points'] == $leaderBoard[$key-1]['points']){
                    $leaderBoard[$key]['position'] = $leaderBoard[$key-1]['position'];
                }else{
                    $position++;
                    $leaderBoard[$key]['position'] = $position;
                }
            }
        }

        self::saveLeaderBoard($leaderBoard, $end_date);

        $leaderBoard = self::addLastPosition($leaderBoard);

        Redis::set('leaderboard', json_encode($leaderBoard));

        return $leaderBoard;
    }

    private static function saveLeaderBoard($leaderBoard, $end_date = null){
        $current_season = Season::where('is_active', 1)->first();

        if ($end_date) {
            $active_weeks = Week::where('season_id', $current_season->id)->where('end_date', '<=', $end_date)->pluck('id')->toArray();
        } else {
            $today = Carbon::now();

            $active_weeks = Week::where('season_id', $current_season->id)->where('end_date', '<=', $today)->orderBy('end_date', 'desc')->first()->id;

            $active_weeks = [$active_weeks];
        }

        foreach($active_weeks as $week_id){
            Leaderboard::updateOrCreate([
                'season_id' => $current_season->id,
                'week_id' => $week_id,
            ], [
                'leaderboard' => json_encode($leaderBoard),
            ]);
        }
    }

    private static function addLastPosition($leaderBoard){
        $current_season = Season::where('is_active', 1)->first();

        $current_week = Week::where('season_id', $current_season->id)->orderBy('end_date', 'desc')->first();

        $last_week = Week::where('season_id', $current_season->id)->where('end_date', '<', $current_week->end_date)->orderBy('end_date', 'desc')->first();

        $last_leaderboard = Leaderboard::where('season_id', $current_season->id)->where('week_id', $last_week->id)->first();

        if (!$last_leaderboard) {
            return $leaderBoard;
        }

        $last_leaderboard = (json_decode($last_leaderboard->leaderboard));

        $last_leaderboard = array_map(function($user){
            return (object) $user;
        }, $last_leaderboard);

        foreach($leaderBoard as &$user){
            $user['diff_from_last_week'] = 0;
            $user_from_last_leaderboard = collect($last_leaderboard)->firstWhere('user_id', $user['user_id']);
            $diff = $user_from_last_leaderboard->position - $user['position'];
            $user['diff_from_last_week'] = $diff;
        }

        return $leaderBoard;
    }
}