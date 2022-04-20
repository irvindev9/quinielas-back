<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


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
                return response()->json(['error' => 'Invalid credentials'], 401);
            }
        }else{
            return response()->json(['error' => 'User not found'], 404);
        }
    }

    public function register(Request $request){
        $request->validate([
            'name' => 'required',
            'email' => 'required|unique:users',
            'password' => 'required',
            'password_confirmation' => 'required|same:password',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json($user, 201);
    }

    public function userProfile(){
        return response()->json(auth()->user());
    }

    public function logout(){
        auth()->user()->tokens->each(function($token, $key){
            $token->delete();
        });

        return response()->json('Logged out successfully', 200);
    }
}
