<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\MHController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\Credit;
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


/**
 * Event Management
 */
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
 * Event Registration Mangement
 */

/**
 * 🔓 Public (Unauthenticated) Event Registration Routes
 */
Route::prefix('public')->group(function () {
    Route::get('{userid}/event-registrations', [EventRegistrationController::class, 'indexPublicRegistrations']);
    Route::post('event-registrations', [EventRegistrationController::class, 'storePublicRegistration']);
    Route::get('{userid}/event-registrations/{event}', [EventRegistrationController::class, 'showPublicRegistration']);
});


/**
 * 🔐 Authenticated Event Registration Routes
 */
Route::middleware(['auth:sanctum'])->group(function () {

    // Club Event Registration (for Club Members)
    Route::get('club/event-registrations', [EventRegistrationController::class, 'indexAuthenticatedClubRegistrations']);
    Route::post('club/event-registrations', [EventRegistrationController::class, 'storeAuthenticatedClubRegistration']);
    Route::get('club/event-registrations/{event}', [EventRegistrationController::class, 'showAuthenticatedClubRegistration']);

    // Member Event Registration (for Music Members)
    Route::get('member/event-registrations', [EventRegistrationController::class, 'indexAuthenticatedMemberRegistrations']);
    Route::post('member/event-registrations', [EventRegistrationController::class, 'storeAuthenticatedMemberRegistration']);
    Route::get('member/event-registrations/{event}', [EventRegistrationController::class, 'showAuthenticatedMemberRegistration']);
});


/**
 * 🛠️ Admin Routes to Manage All Registrations
 */
Route::middleware(['auth:sanctum', 'manage_events'])->prefix('admin')->group(function () {

    // All regisrtation
    Route::get('event-registrations', [EventRegistrationController::class, 'indexAllRegistrations']);
    // Route::put('event-registrations/{eventRegistration}', [EventRegistrationController::class, 'updateRegistration']);
    // Route::delete('event-registrations/{eventRegistration}', [EventRegistrationController::class, 'destroyRegistration']);

    // Unauthenticated User Registrations
    Route::get('public/{id}/event-registrations', [EventRegistrationController::class, 'indexPublicRegistrations']);
    Route::put('public/{id}/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'updatePublicRegistration']);
    Route::delete('{userid}/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'destroyPublicRegistration']);

    // Club Member Registrations
    Route::get('club/event-registrations', [EventRegistrationController::class, 'indexClubRegistrationsForAdmin']);
    Route::put('club/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'updateClubRegistration']);
    Route::delete('club/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'destroyClubRegistration']);

    // Music Member Registrations
    Route::get('member/event-registrations', [EventRegistrationController::class, 'indexMemberRegistrationsForAdmin']);
    Route::put('member/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'updateMemberRegistration']);
    Route::delete('member/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'destroyMemberRegistration']);
});



/**
 * credit management
 */

Route::middleware(['auth:sanctum'])->prefix('credit')->group(function () {
    Route::get('user/', [CreditController::class, 'getMyCredits']);
    Route::get('details/{credit}', [CreditController::class, 'showCreditsDetails']);
});

Route::middleware(['auth:sanctum', 'manage_credits'])->group(function () {
    Route::get('/credit', [CreditController::class, 'index']);
    Route::get('/credit/{credit}', [CreditController::class, 'show']);
    Route::post('/event/credit/assign', [CreditController::class, 'store']);
    Route::post('/event/{event}/credits/update', [CreditController::class, 'update']);
});
