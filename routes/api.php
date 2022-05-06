<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\QuinielaController;

// Public routes
Route::controller(UserController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
});

Route::controller(QuinielaController::class)->group(function () {
    Route::get('quiniela/leaderBoard', 'leaderBoard');
    Route::get('quiniela/weeks', 'weeks');
    Route::get('quiniela/matches/{week_id}', 'matches_of_week');
    Route::get('quiniela/results/{week_id}', 'results_by_week');

    Route::get('backgrounds/images', 'get_all_backgrounds');
});

// Auth routes
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::controller(UserController::class)->group(function () {
        Route::get('user', 'user_profile');
        Route::get('user/score', 'get_score');
        Route::get('logout', 'logout');
    });

    Route::controller(QuinielaController::class)->group(function () {
        Route::get('quiniela/{week_id}', 'week_of_user')->where('week_id', '[0-9]+');
        Route::post('quiniela/{week_id}', 'save_week_of_user')->where('week_id', '[0-9]+');
    });
});

// Admin + Auth routes
Route::group(['middleware' => ['auth:sanctum', 'admin']], function () {
    Route::controller(AdminController::class)->group(function () {
        Route::get('seasons', 'get_seasons');
        Route::put('seasons/{id}', 'update_season');
        Route::put('seasons/{id}/register', 'update_season_register');

        Route::get('weeks', 'get_weeks');
        Route::post('weeks', 'add_week');
        Route::delete('weeks/{id}', 'delete_week');
        Route::put('weeks/{id}', 'update_week_status');

        Route::get('users', 'get_users');

        Route::post('match/{id}', 'add_match');
        Route::get('match/{id}', 'get_match');
        Route::delete('match/{id}', 'delete_match');
        Route::put('match/{id}', 'update_match_status');

        Route::delete('participants/{id}', 'delete_participants');
        Route::put('participants/{id}', 'update_participants');
        Route::put('participants/{id}/password', 'update_user_password');
        Route::put('participants/{id}/name', 'update_user_name');
        Route::get('participants/{id}/login', 'log_as_user_for_admin');
        Route::post('participants/{id}/photo', 'upload_user_photo');

        Route::get('backgrounds', 'get_all_backgrounds');
        Route::delete('backgrounds', 'delete_background');
        Route::post('backgrounds', 'save_background_file');

        Route::post('notifications', 'add_notification');
        Route::get('notifications', 'get_active_notifications');
        Route::delete('notifications/{id}', 'delete_notification');
    });
});