<?php

namespace App\Http\Controllers;

use App\Models\Result;
use App\Models\User;
use App\Models\Week;
use App\Models\Match;
use Illuminate\Http\Request;

class QuinielaController extends Controller
{
    public function week_of_user($week_id)
    {
        $week = Week::where('id', $week_id)->with(['matches.team_1','matches.team_2','matches.result_by_user'])->get();

        return response()->json($week);
    }

    public function save_week_of_user(Request $request, $week_id)
    {
        $user_id = auth()->user()->id;

        foreach($request->quinielas as $quiniela){
            Result::updateOrCreate(
                ['user_id' => $user_id, 'match_id' => $quiniela['id']],
                ['team_id' => $quiniela['result_by_user']['team_id']]
            );
        }

        return response()->json(['success' => true]);
    }

    public function weeks(){
        $weeks = Week::orderBy('end_date', 'desc')->get();

        return response()->json($weeks);
    }

    public function leaderBoard(){
        $Users = User::with('results')->get();
        $matches = Match::all();

        $leaderBoard = [];

        foreach($Users as $user){
            $leaderBoard[$user->id]['points'] = 0;
            $leaderBoard[$user->id]['user_id'] = $user->id;
            $leaderBoard[$user->id]['name'] = $user->name;
            $leaderBoard[$user->id]['img'] = $user->img;
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

        return response()->json($leaderBoard);
    }

    public function results_by_week($week_id){
        $matchs = Match::where('week_id', $week_id)->get();

        $results = User::with(['results' => function($query) use ($matchs){
            $query->whereIn('match_id', $matchs->pluck('id'));
        }])->get();

        // add points column
        foreach($results as $key => $result){
            $results[$key]['points'] = 0;
            foreach($result->results as $res){
                $match = $matchs->where('id', $res->match_id)->first();
                $results[$key]['points'] += ($res->team_id == $match->winner_id) ? 1 : 0;
            }
        }

        return response()->json($results);
    }

    public function matches_of_week($week_id){
        $matchs = Match::with(['team_1', 'team_2'])->where('week_id', $week_id)->get();

        return response()->json($matchs);
    }
}
