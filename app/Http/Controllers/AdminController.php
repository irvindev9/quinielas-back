<?php

namespace App\Http\Controllers;

use App\Models\Season;
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
            return response()->json(['message' => 'Season not found!'], 404);
        }

        // inactive all seasons
        Season::where('is_active', 1)->update(['is_active' => 0]);
        // active the season
        Season::where('id', $id)->update(['is_active' => 1]);

        return response()->json($season, 200);
    }

    public function update_season_register(Request $request, $id){
        $season = Season::find($id);

        if(!$season){
            return response()->json(['message' => 'Season not found!'], 404);
        }

        // active the season
        Season::where('id', $id)->update(['is_register_open' => $request->status]);

        return response()->json($season, 200);
    }
}
