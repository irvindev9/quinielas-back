<?php

namespace App\Http\Controllers;

use App\Models\Week;
use Illuminate\Http\Request;

class QuinielaController extends Controller
{
    public function week_of_user($week_id)
    {
        $week = Week::where('id', $week_id)->with(['matches.result_by_user','matches.team_1','matches.team_2'])->get();

        return response()->json($week);
    }
}
