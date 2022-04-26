<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Models\Week;
use App\Models\Match;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function get_seasons(){
        $seasons = Season::all();
        
        return response()->json($seasons, 200);
    }

    public function update_season(Request $request, $id){
        $season = Season::find($id);

        if(!$season){
            return response()->json(['message' => 'No se encontro la temporada!'], 404);
        }

        Season::where('is_active', 1)->update(['is_active' => 0]);

        Season::where('id', $id)->update(['is_active' => 1]);

        return response()->json($season, 200);
    }

    public function update_season_register(Request $request, $id){
        $season = Season::find($id);

        if(!$season){
            return response()->json(['message' => 'No se encontro la temporada'], 404);
        }

        Season::where('id', $id)->update(['is_register_open' => $request->status]);

        return response()->json($season, 200);
    }

    public function add_week(Request $request){
        $season = Season::where('is_active', 1)->first();

        if(!$season){
            return response()->json(['message' => 'No hay temporada activa'], 404);
        }

        // datetime javascript to php
        $end_date = date('Y-m-d H:i:s', strtotime($request->date));

        $week = Week::create([
            'season_id' => $season->id,
            'name' => $request->name,
            'end_date' => $end_date,
        ]);

        return response()->json($week, 200);
    }

    public function get_weeks(){
        $season = Season::where('is_active', 1)->first();

        $weeks = Week::where('season_id', $season->id)->with(['matches.team_1', 'matches.team_2'])->orderBy('end_date', 'desc')->get();

        return response()->json($weeks, 200);
    }

    public function delete_week($id){
        $week = Week::find($id);

        if(!$week){
            return response()->json(['message' => 'No se encontro la semana'], 401);
        }

        if($week->end_date < date('Y-m-d H:i:s')){
            return response()->json(['message' => 'No se puede eliminar una semana que ya paso'], 401);
        }

        Match::where('week_id', $week->id)->delete();

        $week->delete();

        return response()->json(['message' => 'Semana eliminada'], 200);
    }

    public function update_week_status($id){
        $week = Week::find($id);

        if(!$week){
            return response()->json(['message' => 'No se encontro la semana'], 404);
        }

        Week::where('id', $id)->update(['is_forced_open' => ($week->is_forced_open == 1) ? 0 : 1]);

        return response()->json($week, 200);
    }

    public function get_users(){
        $users = User::all();

        return response()->json($users, 200);
    }

    public function add_match(Request $request, $id){
        $request->validate([
            'team_id' => 'required',
            'team_id_2' => 'required',
        ]);

        $team_1 = Team::where('id', $request->team_id)->first();
        $team_2 = Team::where('id', $request->team_id_2)->first();

        if(isset($team_1) && isset($team_2)){
            $week = Week::where('id', $id)->first();

            if(isset($week)){
                $match = Match::create([
                    'team_id' => $request->team_id,
                    'team_id_2' => $request->team_id_2,
                    'week_id' => $id,
                ]);

                return response()->json($match, 200);
            }else{
                return response()->json(['message' => 'No se encontro la semana'], 401);
            }
        }else{
            return response()->json(['message' => 'No se encontro el equipo'], 402);
        }
    }

    public function get_match($id){
        $week = Week::where('id', $id)->first();

        if(isset($week)){
            $matches = Match::where('week_id', $id)->with('team_1', 'team_2')->get();

            return response()->json($matches, 200);
        }else{
            return response()->json(['message' => 'No se encontro la semana'], 401);
        }
    }

    public function delete_match($id){
        $match = Match::find($id);

        $week = Week::where('id', $match->week_id)->first();

        if(!$match){
            return response()->json(['message' => 'No se encontro el partido'], 404);
        }

        if($week->end_date < date('Y-m-d H:i:s')){
            return response()->json(['message' => 'No se puede eliminar un partido que ya paso'], 401);
        }

        $match->delete();

        return response()->json(['message' => 'Partido eliminado'], 200);
    }

    public function update_match_status(Request $request, $id){
        $match = Match::find($id);

        if(!$match){
            return response()->json(['message' => 'No se encontro el partido'], 404);
        }

        $match->winner_id = $request->winner_id;

        $match->save();

        return response()->json($match, 200);
    }
}
