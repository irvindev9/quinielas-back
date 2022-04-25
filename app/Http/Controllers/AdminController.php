<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\User;
use App\Models\Week;
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

        $weeks = Week::where('season_id', $season->id)->orderBy('end_date', 'desc')->get();

        return response()->json($weeks, 200);
    }

    public function delete_week($id){
        $week = Week::find($id);

        if(!$week){
            return response()->json(['message' => 'No se encontro la semana'], 404);
        }

        $week->delete();

        return response()->json(['message' => 'Semana eliminada'], 200);
    }

    public function get_users(){
        $users = User::all();

        return response()->json($users, 200);
    }
}
