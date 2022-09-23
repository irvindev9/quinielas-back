<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Models\Week;
use App\Models\Play;
use App\Models\Notification;
use App\Models\Result;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

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
        $this->refresh_results();

        if(!$week){
            return response()->json(['message' => 'No se encontro la semana'], 401);
        }

        if($week->end_date < date('Y-m-d H:i:s')){
            return response()->json(['message' => 'No se puede eliminar una semana que ya paso'], 401);
        }

        Play::where('week_id', $week->id)->delete();

        $week->delete();

        return response()->json(['message' => 'Semana eliminada'], 200);
    }

    public function update_week_status(Request $request, $id){
        $request->validate([
            'is_forced_open' => 'required|int',
            'is_forced_open_quiniela' => 'required|int',
        ]);

        $week = Week::find($id);

        if(!$week){
            return response()->json(['message' => 'No se encontro la semana'], 404);
        }

        Week::where('id', $id)->update(['is_forced_open' => $request->is_forced_open, 'is_forced_open_quiniela' => $request->is_forced_open_quiniela]);

        return response()->json($week, 200);
    }

    public function get_users(){
        $users = User::with('team')->orderBy('name', 'asc')->get();

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
                $match = Play::create([
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
            $matches = Play::where('week_id', $id)->with('team_1', 'team_2')->get();

            return response()->json($matches, 200);
        }else{
            return response()->json(['message' => 'No se encontro la semana'], 401);
        }
    }

    public function delete_match($id){
        $match = Play::find($id);

        $week = Week::where('id', $match->week_id)->first();
        $this->refresh_results();

        if(!$match){
            return response()->json(['message' => 'No se encontro el partido'], 404);
        }

        if($week->end_date < date('Y-m-d H:i:s')){
            return response()->json(['message' => 'No se puede eliminar un partido que ya paso'], 401);
        }

        $results = Result::where('match_id', $id)->get();

        foreach($results as $result){
            $result->delete();
        }

        $match->delete();

        return response()->json(['message' => 'Partido eliminado'], 200);
    }

    public function update_match_status(Request $request, $id){
        $match = Play::find($id);
        $this->refresh_results();

        if(!$match){
            return response()->json(['message' => 'No se encontro el partido'], 404);
        }

        $match->winner_id = $request->winner_id;

        $match->save();

        return response()->json($match, 200);
    }

    public function delete_participants($user_id){
        $user = User::find($user_id);
        $this->refresh_results();

        if(!$user){
            return response()->json(['message' => 'No se encontro el usuario'], 404);
        }

        if($user->role_id == 1){
            return response()->json(['message' => 'No se puede eliminar un administrador'], 401);
        }

        $user->results()->delete();

        $user->delete();

        return response()->json(['message' => 'Usuario eliminado'], 200);
    }

    public function update_participants(Request $request, $user_id){
        $user = User::find($user_id);
        $this->refresh_results();

        $params = $request->all()[0];

        if(!$user){
            return response()->json(['message' => 'No se encontro el usuario'], 404);
        }

        $user->update($params);

        return response()->json($user, 200);
    }

    public function update_user_password(Request $request, $user_id){

        $user = User::find($user_id);

        if(!$user){
            return response()->json(['message' => 'No se encontro el usuario'], 404);
        }

        if($user->role_id == 1 && $user->id != auth()->user()->id){
            return response()->json(['message' => 'No se puede cambiar el password de otro administrador'], 401);
        }

        $request->validate([
            'password' => 'required|min:6',
            'password_confirmation' => 'required|min:6|same:password',
        ]);

        $user->password = bcrypt($request->password);

        $user->save();

        return response()->json($user, 200);
    }

    public function update_user_name(Request $request, $user_id){

        $user = User::find($user_id);
        Redis::del('leaderboard');

        if(!$user){
            return response()->json(['message' => 'No se encontro el usuario'], 404);
        }

        $request->validate([
            'name' => 'required|min:3',
        ]);

        $user->name = $request->name;

        $user->save();

        return response()->json($user, 200);
    }

    public function log_as_user_for_admin($user_id){
        $user = User::find($user_id);

        if(!$user){
            return response()->json(['message' => 'No se encontro el usuario'], 404);
        }

        if($user->role_id == 1){
            return response()->json(['message' => 'No se puede loguear como un usuario que es administrador'], 401);
        }

        auth()->user()->tokens->each(function($token, $key){
            $token->delete();
        });

        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user], 200);
    }

    public function save_background_file(Request $request){
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $file = $request->file('file');

        $name = time().'_'.$file->getClientOriginalName();

        $background = Storage::disk('public')->put('backgrounds/'.$name, file_get_contents($file));

        return response()->json($background, 200);
    }

    public function get_all_backgrounds(){
        $files = Storage::disk('public')->files('backgrounds');

        $backgrounds = [];

        foreach($files as $key => $file){
            $type = Storage::disk('public')->mimeType($file);
            if($type == 'image/jpeg' || $type == 'image/png' || $type == 'image/jpg'){
                $dimensions = getimagesize(Storage::disk('public')->path($file));
                $backgrounds[$key] = [
                    'id' => $key,
                    'name' => $file,
                    'url' => Storage::disk('public')->url($file),
                    'size' => round(Storage::disk('public')->size($file) / 1024, 2),
                    'type' => Storage::disk('public')->mimeType($file),
                    'dimensions' => [
                        'width' => $dimensions[0],
                        'height' => $dimensions[1],
                    ],
                    'modified' => Storage::disk('public')->lastModified($file),
                ];
            }
        }

        return response()->json($backgrounds, 200);
    }

    public function delete_background(Request $request){

        $file = Storage::disk('public')->delete($request->name);

        return response()->json(['message' => $file], 200);
    }

    public function upload_user_photo(Request $request, $user_id){
        $user = User::find($user_id);

        if(!$user){
            return response()->json(['message' => 'No se encontro el usuario'], 404);
        }

        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg|max:1024',
        ]);

        $file = $request->file('file');

        $name = time().'_'.$file->getClientOriginalName();

        $path = Storage::disk('public')->put('users/'.$name, file_get_contents($file));

        $photo = Storage::disk('public')->url('users/'.$name);

        $user->img = $photo;

        $user->save();

        return response()->json($user, 200);
    }

    public function add_notification(Request $request){
        
        $request->validate([
            'message' => 'required|min:3',
            'active_to' => 'required',
            'position' => 'required',
            'color' => 'required',
        ]);

        return response()->json(['message' => Notification::create($request->all())], 200);
    }

    public function get_active_notifications(){
        $notifications = Notification::where('active_to', '>=', Carbon::now())->get();

        return response()->json($notifications, 200);
    }

    public function delete_notification($notification_id){
        $notification = Notification::find($notification_id);

        if(!$notification){
            return response()->json(['message' => 'No se encontro la notificacion'], 404);
        }

        $notification->delete();

        return response()->json(['message' => 'Notificacion eliminada'], 200);
    }

    public function get_espn_games($week = 1, $year = 2022){
        $command = escapeshellcmd(env('PYTHON_VERSION', 'python3').' commands/quiniela-scraper/espn_nfl.py '.$week.' '.$year);
        $output = exec($command);

        $output = str_replace('[', '', $output);
        $output = str_replace(']', '', $output);
        $output = explode(',', $output);
        $i = 0;
        $index = 0;

        $games = [];

        $teams = Team::all();

        foreach($output as $key => $game){
            if (($i % 2) == 0) {
                $games[$index][0] = $teams->filter(function($value, $key) use ($game){
                    return stripos($value, str_replace("}", '', str_replace("'", '', explode(': ', $game)[1]))) !== false;
                })->first();
            } else {
                $games[$index][1] = Team::where('name', 'like', '%'.str_replace("}", '', str_replace("'", '', explode(': ', $game)[1])).'%')->first();
                $index++;
            }
            $i++;
        }

        if(count($games) == 0){
            return response()->json(['message' => 'No se encontraron partidos'], 404);
        }

        $season = Season::where('name', 'Temporada '.$year)->first();
        $week = Week::where('name', 'Semana '.$week)->where('season_id', $season->id)->first();

        foreach($games as $key => $game){
            Play::create([
                'team_id' => $game[0]->id,
                'team_id_2' => $game[1]->id,
                'week_id' => $week->id,
            ]);
        }

        return response()->json($games, 200);
    }

    public static function refresh_results(){
        Redis::del('leaderboard');
        foreach(Redis::keys('results_by_week*') as $key){
            Redis::del(str_replace('laravel_database_', '', $key));
        }

        return response()->json(['message' => 'Resultados actualizados'], 200);
    }
}
