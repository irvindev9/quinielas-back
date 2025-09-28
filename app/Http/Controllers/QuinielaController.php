<?php

namespace App\Http\Controllers;

use App\Models\Result;
use App\Models\User;
use App\Models\Week;
use App\Models\Play;
use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\AdminController;
use App\Utils\LeaderBoardHelper;

class QuinielaController extends Controller
{
    public function week_of_user($week_id){
        $week = Week::where('id', $week_id)->with(['matches.team_1','matches.team_2','matches.result_by_user'])->get();

        return response()->json($week);
    }

    public function save_week_of_user(Request $request, $week_id){
        $user_id = auth()->user()->id;

        foreach($request->quinielas as $quiniela){
            Result::updateOrCreate(
                ['user_id' => $user_id, 'match_id' => $quiniela['id']],
                ['team_id' => $quiniela['result_by_user']['team_id']]
            );
        }

        AdminController::refresh_results();

        return response()->json(['success' => true]);
    }

    public function weeks(){
        $active_season = Season::where('is_active', 1)->first()->id;
        $weeks = Week::where('season_id', $active_season)->orderBy('end_date', 'desc')->get();

        return response()->json($weeks);
    }

    public function leaderBoard(){
        $leaderboard = LeaderBoardHelper::getLeaderBoard();

        return response()->json($leaderboard);
    }

    public function results_by_week($week_id){
        if ($results = Redis::get('results_by_week.'.$week_id)) {
            return response()->json(json_decode($results));
        }

        $matchs = Play::where('week_id', $week_id)->get();

        $results = User::with(['results' => function($query) use ($matchs){
            $query->whereIn('match_id', $matchs->pluck('id'));
        }])->where('is_hide', 0)->get();

        // add points column
        foreach($results as $key => $result){
            $results[$key]['points'] = 0;
            foreach($result->results as $res){
                $match = $matchs->where('id', $res->match_id)->first();
                $results[$key]['points'] += ($res->team_id == $match->winner_id) ? 1 : 0;
            }
        }

        Redis::set('results_by_week.'.$week_id, json_encode($results));

        return response()->json($results);
    }

    public function matches_of_week($week_id){
        if($matchs = Redis::get('matches_of_week.'.$week_id)){
            return response()->json(json_decode($matchs));
        }

        $matchs = Play::with(['team_1', 'team_2'])->where('week_id', $week_id)->get();

        Redis::set('matches_of_week.'.$week_id, json_encode($matchs));

        return response()->json($matchs);
    }

    public function get_all_backgrounds(){
        $files = Storage::disk('public')->files('backgrounds');

        $backgrounds = [];

        foreach($files as $key => $file){
            $type = Storage::disk('public')->mimeType($file);
            if($type == 'image/jpeg' || $type == 'image/png' || $type == 'image/jpg'){
                $backgrounds[] = Storage::disk('public')->url($file);
            }
        }

        return response()->json($backgrounds, 200);
    }
}
