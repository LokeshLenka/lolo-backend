<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TeamProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\EBMController;
use App\Http\Controllers\MembershipHeadController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\ManagementProfileController;
use App\Http\Controllers\MusicProfileController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\PublicUserController;
use App\Http\Controllers\UserApprovalController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\Credit;
use App\Models\EventRegistration;
use App\Models\ManagementProfile;
use App\Models\MusicProfile;
use App\Models\TeamProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;

// testing
Route::get('/',function(){
    return response()->json(['message'=>'API is working']);
});

/**
 * --------------------------------------------------------------------------
 * Authentication Routes
 * --------------------------------------------------------------------------
 *
 * These API routes handle user and admin authentication.
 * Each route is protected by rate limiting to prevent abuse.
 *
 * Rate Limits:
 * - /register:     Max 5 requests per minute (to prevent spam registrations)
 * - /login:        Max 30 requests per minute (to prevent brute-force attacks)
 * - /admin/login:  Max 10 requests per minute (stricter protection for admin access)
 */

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:30,1');

Route::post('/admin/login', [AuthController::class, 'adminlogin'])
    ->middleware('throttle:10,1');


/**
 * --------------------------------------------------------------------------
 * Public (Unauthenticated) Routes
 * --------------------------------------------------------------------------
 *
 * These API routes are open to the public and do not require authentication.
 * Each route is rate-limited to prevent abuse.
 *
 * Rate Limits:
 * - /event:         Max 60 requests per minute (to prevent excessive requests)
 * - /blog:          Max 60 requests per minute
 * - /team-profile:  Max 60 requests per minute
 */

/**
 * Event Routes
 */
Route::middleware('throttle:60,1')->controller(EventController::class)->prefix('event')->group(function () {
    Route::get('/', 'index');
    Route::get('/{event}', 'show');
});

/**
 * Blog Routes
 */
Route::middleware('throttle:60,1')->controller(BlogController::class)->prefix('blog')->group(function () {
    Route::get('/', 'index');
    Route::get('/{blog}', 'show');
});

/**
 * Team Profile Routes
 */
Route::middleware('throttle:60,1')->controller(TeamProfileController::class)->prefix('team-profile')->group(function () {
    Route::get('/', 'index');
    Route::get('/{team_profile}', 'show');
});


// additonal middleware is required

Route::apiResource('public-user', PublicUserController::class);

Route::controller(PublicRegistrationController::class)->prefix('public')->group(function () {
    Route::get('{userid}/event-registrations', 'index');
    Route::post('event-registrations', 'store');
    Route::get('{userid}/event-registrations/{event}', 'show');
});
// -------------> these routes pointed to PublicRegistrationController


/**
 * 🔐 Authenticated User Routes
 */
Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {

    Route::controller(AuthController::class)->prefix('auth')->group(function () {
        Route::post('logout', 'logout'); //logout
        Route::post('refresh', 'refresh'); // refresh the token

        Route::get('tokens', 'tokens'); // get all tokens of single user
        Route::delete('tokens/{tokenId}', 'revokeToken'); // deletes the token
    });

    /**
     * club members
     */

    Route::middleware(['valid_club_member'])->prefix('club')->group(function () {

        // club profile management
        Route::get('/{my-profile}', [AuthController::class, 'me']);

        // Club Event Registration (for Club Members)
        Route::controller(EventRegistrationController::class)->prefix('event-registrations')->group(function () {
            Route::get('/', 'indexUserClubRegistrations');
            Route::post('/', 'storeClubRegistration');
            Route::get('/{event_registration}', 'showUserClubRegistration');
        });

        // credits
        Route::prefix('credits')->controller(CreditController::class)->group(function () {
            Route::get('/', 'getUserCredits');
            Route::get('/{credit}', 'showUserCreditsDetails');
        });
    });

    // ------------------------ music members------------------------

    Route::middleware(['valid_music_member'])->prefix('music')->group(function () {

        Route::get('/{my-profile}', [AuthController::class, 'me']); // get the profile details

        // Member Event Registration (for Music Members)
        Route::controller(EventRegistrationController::class)->prefix('event-registrations')->group(function () {
            Route::get('/', 'indexUserMusicRegistrations');
            Route::post('/', 'storeMusicRegistration');
            Route::get('/{event_registration}', 'showUserMusicRegistration');
        });

        // credits
        Route::prefix('credits')->controller(CreditController::class)->group(function () {
            Route::get('/', 'getUserCredits');
            Route::get('/{credit}', 'showUserCreditsDetails');
        });
    });


    // Blog Management
    Route::prefix('blog')->controller(BlogController::class)->group(function () {
        Route::post('/', 'store');
        Route::put('/{blog}', 'update');
        Route::delete('/{blog}', 'destroy');
    });
});

/** -----------------------------------------------------------------------------------------------
 *                                  Admin Routes
 *  -----------------------------------------------------------------------------------------------
 */
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

    Route::controller(AdminController::class)->group(function () {

        // User Approval management
        Route::post('approve-user/{user}', 'approve');
        Route::post('reject-user/{user}', 'reject');
        Route::post('unlock-account/{user}', 'clearLock');

        // Route::get('pending-approvals/', 'getPendingApprovalsForAdmin');

        // Promotion
        Route::prefix('promote/{role}')->whereIn('role', ['ebm', 'credit-manager', 'membership-head'])->group(function () {
            Route::post('/{user}', 'promoteUser');
        });

        //depromotion
        Route::post('de-promote/{user}', 'dePromote');
    });

    Route::middleware('manage_users')->prefix('users')->controller(UserController::class)->group(function () {
        //User Management
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{user}', 'show');
        Route::put('/{user}', 'update');
        Route::delete('/{user}', 'destroy');

        Route::get('/view/stats', 'statistics');

        Route::get('/view/get-pending-approvals', 'getPendingApprovals');
        Route::get('/view/get-users-role/{role}', 'getUsersByRole');
        Route::get('/view/stats', 'statistics');
        // Route::get('/reports/export', 'export');
    });


    Route::prefix('team-profile')->controller(TeamProfileController::class)->group(function () {
        // team profile management
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{team_profile}', 'show');
        Route::put('/{team_profile}', 'update');
        Route::delete('/{team_profile}', 'destroy');
    });

    Route::prefix('user_approval')->controller(UserApprovalController::class)->group(function () {
        // User approval management
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{user}', 'show');
        Route::put('/{user}', 'update');
        Route::delete('/{user}', 'destroy');
    });

    /**
     * Event Management
     */
    Route::prefix('event')->controller(EventController::class)->group(function () {

        Route::middleware('create_events')->group(function () {
            Route::post('/', 'store');
        });

        Route::middleware('manage_events')->group(function () {
            Route::put('/{event}', 'update');
            Route::delete('{event}', 'destroy');
        });
    });


    /**
     * Event Registration Management
     */
    Route::middleware('manage_events')->controller(EventRegistrationController::class)->group(function () {
        // All registrations
        Route::get('event-registrations', 'indexAllRegistrations');

        // Club Member Registrations
        Route::prefix('club/event-registrations')->group(function () {
            Route::get('/', 'indexAllClubRegistrations');
            Route::put('/{eventRegistration}', 'updateClubRegistration');
            Route::delete('/{eventRegistration}', 'destroyClubRegistration');
        });

        // Music Member Registrations
        Route::prefix('music/event-registrations')->group(function () {
            Route::get('/', 'indexAllMusicRegistrations');
            Route::put('/{eventRegistration}', 'updateMusicRegistration');
            Route::delete('/{eventRegistration}', 'destroyMusicRegistration');
        });
    });
});



/**
 * ============================================================================================================================
 *
 * MemberShipHead Routes - All routes prefixed with /membership-head and protected by Sanctum and 'membership_head' middleware
 *
 * ============================================================================================================================
 */

Route::middleware(['auth:sanctum', 'membership_head'])->prefix('membership-head')->group(function () {

    Route::middleware('throttle:30,1')->controller(MembershipHeadController::class)->group(function () {

        //user approval management
        Route::post('approve-user/{user}', 'approve');
        Route::post('reject-user/{user}', 'reject');

        Route::get('pending-approvals', 'getMyPendingApprovals');   // List pending approvals
        Route::get('my-approvals', 'getMyApprovals');             // List EBM's approved/rejected users

        // Promotion
        Route::prefix('promote/{role}')->whereIn('role', ['ebm', 'credit-manager'])->group(function () {
            Route::post('/{user}', 'promoteUser');
        });

        //depromotion
        Route::post('de-promote/{user}', 'dePromote');

        /**
         *  Dashboard stats
         */
        Route::get('dashboard', 'getDashboardStatistics');
    });

    Route::middleware('manage_users', 'throttle:30,1')->prefix('users')->controller(UserController::class)->group(function () {

        //User Management
        Route::get('/', 'index');
        // Route::post('/', 'store'); // Not applicable to membership head
        Route::get('/{user}', 'show');
        Route::put('/{user}', 'update');
        Route::delete('/{user}', 'destroy');

        Route::get('view/stats', 'statistics');
    });

    Route::prefix('team-profile', 'throttle:30,1')->controller(TeamProfileController::class)->group(function () {
        // team profile management
        Route::post('/', 'store');
        Route::put('/{team_profile}', 'update');
        Route::delete('/{team_profile}', 'destroy');
    });
});



/**
 * =========================================================================================================
 *
 *         EBM Routes - All routes prefixed with /ebm and protected by Sanctum and 'ebm' middleware
 *
 * =========================================================================================================
 */
Route::middleware(['auth:sanctum', 'ebm', 'throttle:60,1'])->prefix('ebm')->group(function () {

    /**
     * User Approvals (EBM-specific)
     */
    Route::middleware('throttle:10,1')->controller(EBMController::class)->group(function () {
        Route::post('approve-user/{user}', 'approveUser');        // Approve a user
        Route::post('reject-user/{user}', 'rejectUser');          // Reject a user

        Route::get('pending-approvals', 'getPendingApprovals');   // List pending approvals
        Route::get('my-approvals', 'getMyApprovals');             // List EBM's approved/rejected users

        // User management by EBM
        Route::get('view/my-user-registrations', 'getMyRegistrations');
        Route::post('create/user', 'storeUser');
    });

    /**
     * EBM Dashboard
     */
    Route::get('dashboard', [EBMController::class, 'getDashboardStatistics']); // Dashboard stats

    /**
     * Event Registrations View (Permission: view_registrations)
     */
    Route::middleware('view_registrations')->controller(EventRegistrationController::class)->prefix('event-registrations')->group(function () {
        Route::get('event/{event}', 'showRegistrationsByEvent');  // View registrations by event
        Route::get('{event_registration}', 'showRegistration');   // View single registration
    });

    /**
     * Event Creation (Permission: create_events)
     */
    Route::middleware('create_events', 'throttle:30,1')->group(function () {
        Route::post('event', [EventController::class, 'store']);      // Create a new event
        Route::get('my-events', [EventController::class, 'myEvents']); // View events created by EBM
    });
});




/**
 * Credit Manager Routes
 */

Route::middleware('auth:sanctum')->prefix('credit-manager')->group(function () {
    Route::middleware('view_registrations')->controller(EventRegistrationController::class)->group(function () {

        Route::prefix('event-registrations')->group(function () {
            Route::get('/', 'indexAllRegistrations');

            Route::get('/event/{event}', 'showRegistrationsByEventWithCredits');
            Route::get('/{event_registration}', 'showRegistrationWithCredits');
        });
    });

    // Route::get('/event-registrations/event/{event}', [EventRegistrationController::class, 'showRegistrationsByEvent']);
    // Route::get('/event-registrations/{event_registration}', [EventRegistrationController::class, 'showRegistration']);


    Route::middleware('manage_credits')->controller(CreditController::class)->group(function () {

        Route::get('/credits', 'index'); // Credit listing & detail

        Route::get('show-credit/{credit}', 'show'); // get the deatails of specific credit

        Route::get('/credit/{event}', 'indexCreditsByEvent'); // List all credits for an event

        Route::delete('credits/{credit}', 'destroy'); // delete a credit

        Route::prefix('event/{event}/credits')->group(function () {
            // Specific routes first
            Route::put('/batch', 'updateMultiple'); // Update many credits
            Route::post('/batch', 'storeMultiple'); // Assign multiple credits
            Route::delete('/batch', 'destroyMultiple'); // Delete many credits

            // Then dynamic routes
            Route::post('/', 'store');
            Route::put('/{credit}', 'update');
        });
    });
});


// admin,ebm,credit_manager
// Route::middleware(['auth:sanctum', 'view_registrations'])->prefix('view')->group(function () {

//     Route::get('club/event-registrations/{event}', [EventRegistrationController::class, 'showClubRegistrationsByEvent']);
//     Route::get('member/event-registrations/{event}', [EventRegistrationController::class, 'showMusicRegistrationsByEvent']);

//     Route::get('club/event-registrations/{event_registration}', [EventRegistrationController::class, 'showClubRegistration']);
//     Route::get('member/event-registrations/{event_registration}', [EventRegistrationController::class, 'showMusicRegistration']);
// });

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
// Route::get('member/event-registrations', [EventRegistrationController::class, 'indexAllMusicRegistrations']);
// Route::put('member/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'updateMusicRegistration']);
// Route::delete('member/event-registrations/{eventRegistration}', [EventRegistrationController::class, 'destroyMusicRegistration']);
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








// View single event

// Protected routes (auth and manage_events middleware)
// Route::middleware(['auth:sanctum', 'create_events'])->group(function () {});

// Route::middleware(['auth:sanctum', 'manage_events'])->group(function () {
//     Route::put('/admin/events/{event}', [EventController::class, 'update']);
//     Route::delete('/admin/events/{event}', [EventController::class, 'destroy']);
// });


/**
 * Event Registration Mangement
 */


// Route::middleware(['auth:sanctum', 'manage_credits'])->prefix('events/{event}/credits')->name('credits.')->group(function () {
//     // Credit listing & detail (optional - per event basis)
//     Route::get('/', [CreditController::class, 'index'])->name('index');           // List all credits for an event
//     Route::get('/{credit}', [CreditController::class, 'show'])->name('show');     // Show a specific credit

//     // Create single or multiple credits
//     Route::post('/', [CreditController::class, 'store'])->name('store');          // Assign one credit
//     Route::post('/batch', [CreditController::class, 'storeMultiple'])->name('storeMultiple'); // Assign multiple credits

//     // Update single or multiple credits
//     Route::put('/{credit}', [CreditController::class, 'update'])->name('update'); // Update one credit
//     Route::put('/batch', [CreditController::class, 'updateMultiple'])->name('updateMultiple'); // Update many credits

//     // Delete single or multiple credits
//     Route::delete('/{credit}', [CreditController::class, 'destroy'])->name('destroy');         // Delete one credit
//     Route::delete('/batch', [CreditController::class, 'destroyMultiple'])->name('destroyMultiple'); // Delete many
// });

// Route::middleware(['auth:sanctum'])->group(function () {
//     Route::prefix('auth')->group(function () {
//         Route::post('logout', [AuthController::class, 'logout']);
//         Route::post('refresh', [AuthController::class, 'refresh']);
//         Route::get('me', [AuthController::class, 'me']);
//         Route::get('tokens', [AuthController::class, 'tokens']);
//         Route::delete('tokens/{tokenId}', [AuthController::class, 'revokeToken']);
//     });
// });





// Route::get('/test-time', function () {
//     return response()->json([
//         'now' => now()->toDateTimeString(),             // Should be in IST
//         'utc_now' => now('UTC')->toDateTimeString(),    // Will be in UTC
//     ]);
// });

// Route::get('/getsubrole/{user}', function (User $user) {
//     return response()->json([
//         'sub_role' => $user->isDrummer(),
//     ]);
// });

Route::get('/uuu/{credit}', [CreditController::class, 'uuu']);
