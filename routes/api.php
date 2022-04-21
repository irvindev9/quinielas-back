<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;

Route::post('register', [UserController::class, 'register']);

Route::post('login', [UserController::class, 'login']);

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('user', [UserController::class, 'userProfile']);
    Route::get('logout', [UserController::class, 'logout']);
});

Route::group(['middleware' => ['auth:sanctum', 'admin']], function () {
    Route::controller(AdminController::class)->group(function () {
        Route::get('seasons', [AdminController::class, 'get_seasons']);
        Route::put('seasons/{id}', [AdminController::class, 'update_season']);
        Route::put('seasons/{id}/register', [AdminController::class, 'update_season_register']);
    });
});