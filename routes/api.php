<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\MembershipHeadController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\PublicUserController;
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

Route::post('/admin/login', [AuthController::class, 'adminlogin']);

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


/**
 * Admin Routes
 */
Route::middleware(['auth:sanctum', 'admin'])->group(function () {

    Route::prefix('admin')->controller(AdminController::class)->group(function () {
        Route::post('approve-user/{user}', 'approve');
        Route::post('reject-user/{user}', 'reject');
        Route::post('unlock-account/{user}', 'clearLock');

        // EBM
        Route::post('create-ebm/music', 'createEBMWithMusic');
        Route::post('create-ebm/management', 'createEBMWithManagement');
        Route::post('promote/ebm/{user}', 'promoteEBM');
        Route::delete('delete-ebm/{user}', 'deleteEBM');

        // Credit Manager
        Route::post('create-credit-manager/music', 'createCreditManagerWithMusic');
        Route::post('create-credit-manager/management', 'createCreditManagerWithManagement');
        Route::post('promote/credit-manager/{user}', 'promoteCreditManager');
        Route::delete('delete-credit-manager/{user}', 'deleteCreditManager');

        // Membership Head
        Route::post('create-membership-head/music', 'createMemberShipHeadWithMusic');
        Route::post('create-membership-head/management', 'createMemberShipHeadWithManagement');
        Route::post('promote/membership-head/{user}', 'promoteMembershipHead');
        Route::delete('delete-membership-head/{user}', 'deleteMemberShipHead');
    });

    /**
     * Event Management
     */
    Route::put('/event/{event}', [EventController::class, 'update']);
    Route::delete('/event/{event}', [EventController::class, 'destroy']);

    /**
     * 🛠️ Admin Routes to Manage All Registrations
     */
    Route::get('event-registrations', [EventRegistrationController::class, 'indexAllRegistrations']);

    // Club Member Registrations
    Route::get('club/event-registrations', [EventRegistrationController::class, 'indexAllClubRegistrations']);
    Route::put('club/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'updateClubRegistration']);
    Route::delete('club/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'destroyClubRegistration']);

    // Music Member Registrations
    Route::get('member/event-registrations', [EventRegistrationController::class, 'indexAllMemberRegistrations']);
    Route::put('member/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'updateMemberRegistration']);
    Route::delete('member/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'destroyMemberRegistration']);
});

/**
 * Membership Head Routes
 */

Route::middleware(['auth:sanctum', 'membership_head'])->group(function () {

    Route::prefix('membership')->controller(MembershipHeadController::class)->group(function () {

        //user management
        Route::post('approve-user/{user}', 'approve');
        Route::post('reject-user/{user}', 'reject');

        // EBM
        Route::post('create-ebm/music', 'createEBMWithMusic');
        Route::post('create-ebm/management', 'createEBMWithManagement');
        Route::post('promote/ebm/{user}', 'promoteEBM');
        Route::delete('delete-ebm/{user}', 'deleteEBM');

        // Credit Manager
        Route::post('create-credit-manager/music', 'createCreditManagerWithMusic');
        Route::post('create-credit-manager/management', 'createCreditManagerWithManagement');
        Route::post('promote/credit-manager/{user}', 'promoteCreditManager');
        Route::delete('delete-credit-manager/{user}', 'deleteCreditManager');
    });
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

// Route::middleware(['auth:sanctum', 'manage_events'])->group(function () {
//     Route::put('/admin/events/{event}', [EventController::class, 'update']);
//     Route::delete('/admin/events/{event}', [EventController::class, 'destroy']);
// });


/**
 * Event Registration Mangement
 */

/**
 * 🔓 Public (Unauthenticated) Event Registration Routes
 */
// Route::prefix('public')->group(function () {
//     Route::get('{userid}/event-registrations', [EventRegistrationController::class, 'indexPublicRegistrations']);
//     Route::post('event-registrations', [EventRegistrationController::class, 'storePublicRegistration']);
//     Route::get('{userid}/event-registrations/{event}', [EventRegistrationController::class, 'showPublicRegistration']);
// }); -------------> these routes pointed to PublicRegistrationController


/**
 * 🔐 Authenticated Event Registration Routes
 */
Route::middleware(['auth:sanctum'])->group(function () {

    // Club Event Registration (for Club Members)
    Route::get('club/event-registrations', [EventRegistrationController::class, 'indexUserClubRegistrations']);
    Route::post('club/event-registrations', [EventRegistrationController::class, 'storeClubRegistration']);
    Route::get('club/event-registrations/{event_registration}', [EventRegistrationController::class, 'showUserClubRegistration']);

    // Member Event Registration (for Music Members)
    Route::get('member/event-registrations', [EventRegistrationController::class, 'indexUserMemberRegistrations']);
    Route::post('member/event-registrations', [EventRegistrationController::class, 'storeMemberRegistration']);
    Route::get('member/event-registrations/{event_registraton}', [EventRegistrationController::class, 'showUserMemberRegistration']);

    //credits
    Route::prefix('credits')->group(function () {
        Route::get('/', [CreditController::class, 'getUserCredits']);
        Route::get('/{credit}', [CreditController::class, 'showUserCreditsDetails']);
    });
});

// admin,ebm,credit_manager
Route::middleware(['auth:sanctum', 'view_registrations'])->prefix('view')->group(function () {

    Route::get('club/event-registrations/{event}', [EventRegistrationController::class, 'showClubRegistrationsByEvent']);
    Route::get('member/event-registrations/{event}', [EventRegistrationController::class, 'showMemberRegistrationsByEvent']);

    Route::get('club/event-registrations/{event_registration}', [EventRegistrationController::class, 'showClubRegistration']);
    Route::get('member/event-registrations/{event_registration}', [EventRegistrationController::class, 'showMemberRegistration']);
});

/**
 * 🛠️ Admin Routes to Manage All Registrations
 */
// Route::middleware(['auth:sanctum', 'manage_events'])->prefix('admin')->group(function () {

// Unauthenticated User Registrations
// Route::get('public/{id}/event-registrations', [EventRegistrationController::class, 'indexPublicRegistrations']);
// Route::put('public/{id}/event-registrations/{event_registration}', [EventRegistrationController::class, 'updatePublicRegistration']);
// Route::delete('{userid}/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'destroyPublicRegistration']);

// Route::get('event-registrations', [EventRegistrationController::class, 'indexAllRegistrations']);

// // Club Member Registrations
// Route::get('club/event-registrations', [EventRegistrationController::class, 'indexAllClubRegistrations']);
// Route::put('club/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'updateClubRegistration']);
// Route::delete('club/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'destroyClubRegistration']);

// // Music Member Registrations
// Route::get('member/event-registrations', [EventRegistrationController::class, 'indexAllMemberRegistrations']);
// Route::put('member/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'updateMemberRegistration']);
// Route::delete('member/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'destroyMemberRegistration']);
// });



/**
 * credit management
 */

// Route::middleware(['auth:sanctum'])->prefix('credits')->group(function () {
//     Route::get('/', [CreditController::class, 'getUserCredits']);
//     Route::get('/{credit}', [CreditController::class, 'showUserCreditsDetails']);
// });

// Route::middleware(['auth:sanctum', 'manage_credits'])->group(function () {
//     Route::get('/credit', [CreditController::class, 'index']);
//     Route::get('/credit/{credit}', [CreditController::class, 'show']);
//     Route::post('/event/{event}/credit/assign', [CreditController::class, 'store']);
//     Route::put('/event/{event}/credit/update', [CreditController::class, 'update']);
//     Route::delete('/event/{event}/credit/', [CreditController::class, 'destroy']);

//     Route::post('/event/{event}/credits/assign', [CreditController::class, 'storeMultiple']);
//     Route::put('/event/{event}/credits/update', [CreditController::class, 'updateMultiple']);
//     Route::delete('/event/{event}/credits/', [CreditController::class, 'destroyMultiple']);


//     // Route::get('/')
// });

Route::middleware(['auth:sanctum', 'manage_credits'])->prefix('events/{event}/credits')->name('credits.')->group(function () {
    // Credit listing & detail (optional - per event basis)
    Route::get('/', [CreditController::class, 'index'])->name('index');           // List all credits for an event
    Route::get('/{credit}', [CreditController::class, 'show'])->name('show');     // Show a specific credit

    // Create single or multiple credits
    Route::post('/', [CreditController::class, 'store'])->name('store');          // Assign one credit
    Route::post('/batch', [CreditController::class, 'storeMultiple'])->name('storeMultiple'); // Assign multiple credits

    // Update single or multiple credits
    Route::put('/{credit}', [CreditController::class, 'update'])->name('update'); // Update one credit
    Route::put('/batch', [CreditController::class, 'updateMultiple'])->name('updateMultiple'); // Update many credits

    // Delete single or multiple credits
    Route::delete('/{credit}', [CreditController::class, 'destroy'])->name('destroy');         // Delete one credit
    Route::delete('/batch', [CreditController::class, 'destroyMultiple'])->name('destroyMultiple'); // Delete many
});


Route::apiResource('public-user', PublicUserController::class);


Route::get('/test-time', function () {
    return response()->json([
        'now' => now()->toDateTimeString(),             // Should be in IST
        'utc_now' => now('UTC')->toDateTimeString(),    // Will be in UTC
    ]);
});

Route::get('/getsubrole/{user}', function (User $user) {
    return response()->json([
        'sub_role' => $user->isDrummer(),
    ]);
});
