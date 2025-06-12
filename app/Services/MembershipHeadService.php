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

    public function approveUser(User $user): void
    {
        $this->approve($user, 'managebymh');
    }

    public function rejectUser(User $user): void
    {
        $this->reject($user, 'managebymh');
    }

    /**
     * EBM
     */

    public function createEBMWithMusic(array $data): User
    {
        $this->authorizeMembershipHead();

        return $this->createUserWithProfile(UserRoles::ROLE_MUSIC, PromotedRole::EXECUTIVE_BODY_MEMBER, $data);
    }

    public function createEBMWithManagement(array $data): User
    {
        $this->authorizeMembershipHead();

        return $this->createUserWithProfile(UserRoles::ROLE_MANAGEMENT, PromotedRole::EXECUTIVE_BODY_MEMBER, $data);
    }

    public function promoteUserAsEBM(User $user): void
    {
        $this->authorizeMembershipHead();
        $this->promoteUser($user, PromotedRole::EXECUTIVE_BODY_MEMBER);
    }

    public function deleteEBM(User $user): void
    {
        $this->authorizeMembershipHead();

        if (! $user->hasPromotedRole(PromotedRole::EXECUTIVE_BODY_MEMBER)) {
            throw new \Exception("User is not an EBM.");
        }

        $this->deleteUserWithProfiles($user);
    }

    /**
     * Credit manager
     */
    public function createCreditManagerWithMusic(array $data): User
    {
        $this->authorizeMembershipHead();

        return $this->createUserWithProfile(UserRoles::ROLE_MUSIC, PromotedRole::CREDIT_MANAGER, $data);
    }

    public function createCreditManagerWithManagement(array $data): User
    {
        $this->authorizeMembershipHead();

        return $this->createUserWithProfile(UserRoles::ROLE_MANAGEMENT, PromotedRole::CREDIT_MANAGER, $data);
    }

    public function promoteUserAsCreditManager(User $user): void
    {
        $this->authorizeMembershipHead();
        $this->promoteUser($user, PromotedRole::CREDIT_MANAGER);
    }

    public function deleteCreditManager(User $user): void
    {
        $this->authorizeMembershipHead();

        if (! $user->hasPromotedRole(PromotedRole::CREDIT_MANAGER)) {
            throw new \Exception("User is not a Credit Manager.");
        }

        $this->deleteUserWithProfiles($user);
    }

    // --- PRIVATE HELPERS ---

    private function authorizeMembershipHead(): void
    {
        Gate::authorize('membershipHeadOnly', Auth::user());
    }
}
