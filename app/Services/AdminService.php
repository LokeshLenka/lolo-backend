<?php

namespace App\Services;

use App\Http\Controllers\Traits\ApproveUsers;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AdminService
{
    use ApproveUsers;

    public function approveUser(User $user):void
    {
       $this->approve($user,'managebyadmin');
    }

    public function rejectUser(User $user): void
    {
        $this->reject($user,'managebyadmin');
    }

    public function clearAccountLock(User $user): void
    {
        Gate::authorize('clearlock', $user);

        if (!$user->isAccountLocked()) {
            throw new \Exception("Account already unlocked.");
        }

        $user->resetFailedAttempts();
    }
}
