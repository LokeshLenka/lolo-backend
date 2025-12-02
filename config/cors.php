<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'api/*',               // Covers all standard API routes
        'sanctum/csrf-cookie',
        'register',            // Explicitly allow register
        'login',               // Explicitly allow login
        'auth/*',              // Covers auth/logout, auth/refresh
        'public/*',            // Covers public event registrations
        'club/*',              // Covers club member actions
        'music/*',             // Covers music member actions
        'admin/*',             // Covers all admin actions
        'ebm/*',               // Covers EBM actions
        'membership-head/*',   // Covers membership head actions
        'credit-manager/*',    // Covers credit manager actions
        'blog/*',              // Covers blog posts
        'events/*',            // Covers event actions
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:5173'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
