<?php

namespace App\Services;

use App\Enums\PromotedRole;
use App\Enums\UserRoles;
use Illuminate\Support\Facades\Gate;
use Auth;
use App\Models\User;
use App\Http\Controllers\Traits\ApproveUsers;
use App\Http\Controllers\Traits\CreatesUser;
use App\Http\Controllers\Traits\GetPendingApprovals;
use App\Http\Controllers\Traits\HandlesUserProfiles;
use App\Http\Controllers\Traits\MyApprovals;
use App\Http\Controllers\Traits\PromoteUsers;

class MembershipHeadService
{
    use ApproveUsers,
        CreatesUser,
        HandlesUserProfiles,
        PromoteUsers,
        MyApprovals,
        GetPendingApprovals;

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

    public function getApprovals()
    {
        return $this->MemberShipHeadApprovals();
    }

    public function getPendingApprovals()
    {
        return $this->getPendingApprovalsForMemberShipHead();
    }

    // --- PRIVATE HELPERS ---

    private function authorizeMembershipHead(): void
    {
        Gate::authorize('membershipHeadOnly', Auth::user());
    }
}
