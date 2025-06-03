<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\MHController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;

Route::get('/', function (Request $request) {
    return response()->json([
        'status' => 200,
        'message' => 'Successfully connected!'

    ]);
});

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::get('/logout{user}', [AuthController::class, 'logout']);

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('tokens', [AuthController::class, 'tokens']);
        Route::delete('tokens/{tokenId}', [AuthController::class, 'revokeToken']);
    });
});

Route::post('/admin/login', [AuthController::class, 'adminlogin']);


Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/admin/users/{user}/approve', [AdminController::class, 'approve']);
    Route::post('/admin/users/{user}/reject', [AdminController::class, 'reject']);
    Route::post('/admin/users/{user}/clearlock', [AdminController::class, 'clearlock']);

    Route::put('/event/{event}', [EventController::class, 'update']);
    Route::delete('/event/{event}', [EventController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'membership_head'])->group(function () {
    Route::post('/mh/users/{user}/approve', [MHController::class, 'approve']);
    Route::post('/mh/users/{user}/reject', [MHController::class, 'reject']);
});





// Route::apiResource('/blog', BlogController::class);

// Route::apiResource('/team', TeamController::class);

Route::get('/event', [EventController::class, 'index']);        // List all events
Route::get('/event/{event}', [EventController::class, 'show']); // View single event

// Protected routes (auth and manage_events middleware)
Route::middleware(['auth:sanctum', 'create_events'])->group(function () {
    Route::post('/event', [EventController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'manage_events'])->group(function () {
    Route::put('/admin/events/{event}', [EventController::class, 'update']);
    Route::delete('/admin/events/{event}', [EventController::class, 'destroy']);
});

/**
 * credit management
 */

Route::middleware(['auth:scantum'])->group(function () {
    Route::get('/credit', [CreditController::class, 'index']);
    Route::get('/credit/{user}', [CreditController::class, 'getusercredits']);
    Route::get('/credit/{credit}', [CreditController::class, 'showusercredits']);
});

Route::middleware(['auth:scantum', 'manage_credits'])->group(function () {});
