<?php

namespace App\Http\Controllers;

use App\Models\Result;
use App\Models\Week;
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
        $weeks = Week::all();

        return response()->json($weeks);
    }
}
