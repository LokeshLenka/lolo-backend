<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\MHController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('/', function (Request $request) {
    return response()->json([
        'status' => 200,
        'message' => 'Successfully connected!'

    ]);
});

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

// Route::get('/logout{user}', [AuthController::class, 'logout']);

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

// Route::middleware(['auth:sanctum'])->post('/admin/users/{user}/clear-lock', function (User $user, Request $request) {
//     app(\App\Services\AuthService::class)->clearAccountLock($user);

//     return response()->json([
//         'message' => 'Account lock cleared.',
//     ]);
// });

Route::post('/admin/login', [AuthController::class, 'adminlogin']);


Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/admin/users/{user}/approve', [AdminController::class, 'approve']);
    Route::post('/admin/users/{user}/reject', [AdminController::class, 'reject']);
    Route::post('/admin/users/{user}/clearlock', [AdminController::class, 'clearlock']);
});

Route::middleware(['auth:sanctum', 'membershiphead'])->group(function () {
    Route::post('/mh/users/{user}/approve', [MHController::class, 'approve']);
    Route::post('/mh/users/{user}/reject', [MHController::class, 'reject']);
});





// Route::apiResource('/blog', BlogController::class);

// Route::apiResource('/team', TeamController::class);

// Route::apiResource('/event', EventController::class);
