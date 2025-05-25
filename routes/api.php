<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TeamController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('/', function (Request $request) {
    return response()->json([
        'status' => 200,
        'message' => 'Successfully connected!'

    ]);
});

Route::post('/register',[AuthController::class,'register']);

Route::post('/login',[AuthController::class,'login']);

Route::get('/logout',[AuthController::class,'logout']);

// Route::apiResource('/blog', BlogController::class);

// Route::apiResource('/team', TeamController::class);

// Route::apiResource('/event', EventController::class);

