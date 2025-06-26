<?php

namespace App\Services;

use App\Enums\PromotedRole;
use App\Enums\UserRoles;
use Illuminate\Support\Facades\Gate;
use Auth;
use App\Models\User;
use App\Http\Controllers\Traits\ApproveUsers;
use App\Http\Controllers\Traits\CreatesUser;
use App\Http\Controllers\Traits\HandlesUserProfiles;
use App\Http\Controllers\Traits\PromoteUsers;

class MembershipHeadService
{
    use ApproveUsers,
        CreatesUser,
        HandlesUserProfiles,
        PromoteUsers;

    public function approveUser(User $user, string $remarks): void
    {
        $this->approveByMemberShipHead($user, 'managebymh', $remarks);
    }

    public function rejectUser(User $user, string $remarks): void
    {
        $this->rejectByMemberShipHead($user, 'managebymh', $remarks);
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

    private function authorizeMembershipHead(): void
    {
        Gate::authorize('membershipHeadOnly', Auth::user());
    }
}
