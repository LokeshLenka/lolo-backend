<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Support\Facades\Auth;
use App\Models\User;

trait ChecksAdmin
{
    public function isAdmin(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }
}
