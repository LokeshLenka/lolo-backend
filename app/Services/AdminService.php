<?php

namespace App\Services;

use App\Enums\PromotedRole;
use App\Enums\UserRoles;
use App\Http\Controllers\Traits\HandlesUserProfiles;
use App\Http\Controllers\Traits\CreatesUser;
use App\Http\Controllers\Traits\ApproveUsers;
use App\Http\Controllers\Traits\GetPendingApprovals;
use App\Http\Controllers\Traits\PromoteUsers;
use App\Models\User;
use App\Models\ManagementProfile;
use App\Models\MusicProfile;
use App\Policies\UserPolicy;
use Auth;
use DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminService
{
    use ApproveUsers,
        CreatesUser,
        HandlesUserProfiles,
        PromoteUsers,
        GetPendingApprovals;

    public function approveUser(User $user, string $remarks): void
    {
        $this->approveByAdmin($user, 'managebyadmin', $remarks);
    }

    public function rejectUser(User $user, string $remarks): void
    {
        $this->rejectByAdmin($user, 'managebyadmin', $remarks);
    }

    public function clearAccountLock(User $user): void
    {
        Gate::authorize('clearlock', $user);

        if (!$user->isAccountLocked()) {
            throw new \Exception("Account already unlocked.");
        }

        $user->resetFailedAttempts();

        $username = $user->username;
        $ipAddress = $user->last_login_ip;

        RateLimiter::clear("login.{$username}.{$ipAddress}");
    }

    public function promote(User $user, PromotedRole $promotedRole)
    {
        $this->promoteUser($user, $promotedRole);
    }


    public function dePromoteUser(User $user): void
    {
        $this->removePromotion($user);
    }
    // --- PRIVATE HELPERS ---

    private function authorizeAdmin(): void
    {
        Gate::authorize('adminOnly', Auth::user());
    }
}
