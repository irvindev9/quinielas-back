<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\User;
use App\Models\Week;
use App\Models\Play;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\AdminController;



class UserController extends Controller
{
    public function login(Request $request){
        $request->validate([
            'email' => 'required',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if($user){
            if(Hash::check($request->password, $user->password)){
                $token = $user->createToken('authToken')->plainTextToken;
                return response()->json(['token' => $token], 200);
            }else{
                return response()->json(['message' => 'Credenciales invalidas'], 401);
            }
        }else{
            return response()->json(['message' => 'Usuario no encontrado'], 401);
        }
    }

    public function register(Request $request){
        if(!$this->is_register_open()){
            return response()->json(['message' => 'El registro esta cerrado'], 401);
        }

        $season = Season::where('is_active', 1)->first();

        if(!$season){
            return response()->json(['message' => 'No hay temporada activa'], 404);
        }

        if($season->is_register_open == 0){
            return response()->json(['message' => 'El registro esta cerrado!'], 404);
        }

        $request->validate([
            'name' => 'required',
            'email' => 'required|unique:users',
            'password' => 'required',
            'password_confirmation' => 'required|same:password',
            'favorite_team' => 'required'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'team_id' => $request->favorite_team
        ]);

        $token = $user->createToken('authToken')->plainTextToken;

        AdminController::refresh_results();

        return response()->json(['token' => $token, 'user' => $user], 200);
    }

    public function user_profile(){
        $user = User::with('team')->find(auth()->user()->id);

        return response()->json($user);
    }

    public function logout(){
        auth()->user()->tokens->each(function($token, $key){
            $token->delete();
        });

        return response()->json('Logged out successfully', 200);
    }

    public function get_score(){
        $user = User::with('results')->find(auth()->user()->id);

        $is_paid = $user->is_paid;

        $active_season = Season::where('is_active', 1)->first();
        $weeks_id = Week::where('season_id', $active_season->id)->pluck('id')->toArray();
        $matches = Play::whereIn('week_id', $weeks_id)->get();

        $score = 0;

        foreach($matches as $match){
            $result = $user->results()->where('match_id', $match->id)->first();

            if(isset($match->winner_id)){
                if($match->winner_id == $result->team_id){
                    $score++;
                }
            }
        }

        return response()->json(['score' => $score, 'is_paid' => $is_paid]);
    }

    public function is_register_open(){
        $season = Season::where('is_active', 1)->first()->is_register_open;

        if($season === 0){
            return false;
        }

        return true;
    }
}
