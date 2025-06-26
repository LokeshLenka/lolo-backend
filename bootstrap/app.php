<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'membership_head' => \App\Http\Middleware\EnsureMembershipHead::class,
            'ebm' => \App\Http\Middleware\EnsureUserIsExecutiveBodyMember::class,
            'valid_club_member' => \App\Http\Middleware\EnsureUserIsValidClubMember::class,
            'valid_music_member' => \App\Http\Middleware\EnsureUserIsValidMusicMember::class,
            'create_events' => \App\Http\Middleware\EnsureUserCanCreateEvents::class,
            'manage_events' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'view_registrations' => \App\Http\Middleware\EnsureUserCanViewRegistrations::class,
            'manage_credits' => \App\Http\Middleware\EnsureUserCanManageCredits::class,
            'approve_users_by_ebm' => \App\Http\Middleware\EnsureUserIsExecutiveBodyMember::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
