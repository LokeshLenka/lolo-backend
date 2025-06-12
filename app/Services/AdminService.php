<?php

namespace App\Services;

use App\Enums\PromotedRole;
use App\Enums\UserRoles;
use App\Http\Controllers\Traits\HandlesUserProfiles;
use App\Http\Controllers\Traits\CreatesUser;
use App\Http\Controllers\Traits\ApproveUsers;
use App\Http\Controllers\Traits\PromoteUsers;
use App\Models\User;
use App\Models\ManagementProfile;
use App\Models\MusicProfile;
use App\Policies\UserPolicy;
use Auth;
use DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminService
{
    use ApproveUsers,
        CreatesUser,
        HandlesUserProfiles,
        PromoteUsers;

    public function approveUser(User $user): void
    {
        $this->approve($user, 'managebyadmin');
    }

    public function rejectUser(User $user): void
    {
        $this->reject($user, 'managebyadmin');
    }

    public function clearAccountLock(User $user): void
    {
        Gate::authorize('clearlock', $user);

        if (!$user->isAccountLocked()) {
            throw new \Exception("Account already unlocked.");
        }

        $user->resetFailedAttempts();
    }



    /**
     * EBM
     */

    public function createEBMWithMusic(array $data): User
    {
        $this->authorizeAdmin();

        return $this->createUserWithProfile(UserRoles::ROLE_MUSIC, PromotedRole::EXECUTIVE_BODY_MEMBER, $data);
    }

    public function createEBMWithManagement(array $data): User
    {
        $this->authorizeAdmin();

        return $this->createUserWithProfile(UserRoles::ROLE_MANAGEMENT, PromotedRole::EXECUTIVE_BODY_MEMBER, $data);
    }

    public function promoteUserAsEBM(User $user): void
    {
        $this->authorizeAdmin();
        $this->promoteUser($user, PromotedRole::EXECUTIVE_BODY_MEMBER);
    }

    public function deleteEBM(User $user): void
    {
        $this->authorizeAdmin();

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
        $this->authorizeAdmin();

        return $this->createUserWithProfile(UserRoles::ROLE_MUSIC, PromotedRole::CREDIT_MANAGER, $data);
    }

    public function createCreditManagerWithManagement(array $data): User
    {
        $this->authorizeAdmin();

        return $this->createUserWithProfile(UserRoles::ROLE_MANAGEMENT, PromotedRole::CREDIT_MANAGER, $data);
    }

    public function promoteUserAsCreditManager(User $user): void
    {
        $this->authorizeAdmin();
        $this->promoteUser($user, PromotedRole::CREDIT_MANAGER);
    }

    public function deleteCreditManager(User $user): void
    {
        $this->authorizeAdmin();

        if (! $user->hasPromotedRole(PromotedRole::CREDIT_MANAGER)) {
            throw new \Exception("User is not a Credit Manager.");
        }

        $this->deleteUserWithProfiles($user);
    }

    /**
     * MemberShipHead
     */

    public function createMemberShipHeadWithMusic(array $data): User
    {
        $this->authorizeAdmin();

        return $this->createUserWithProfile(UserRoles::ROLE_MUSIC, PromotedRole::CREDIT_MANAGER, $data);
    }

    public function createMemberShipHeadWithManagement(array $data): User
    {
        $this->authorizeAdmin();

        return $this->createUserWithProfile(UserRoles::ROLE_MANAGEMENT, PromotedRole::CREDIT_MANAGER, $data);
    }

    public function promoteUserAsMemberShipHead(User $user): void
    {
        $this->authorizeAdmin();
        $this->promoteUser($user, PromotedRole::MEMBERSHIP_HEAD);
    }

    public function deleteMemberShipHead(User $user): void
    {
        $this->authorizeAdmin();

        if (! $user->hasPromotedRole(PromotedRole::CREDIT_MANAGER)) {
            throw new \Exception("User is not a Credit Manager.");
        }

        $this->deleteUserWithProfiles($user);
    }

    // --- PRIVATE HELPERS ---

    private function authorizeAdmin(): void
    {
        Gate::authorize('adminOnly', Auth::user());
        // return Auth::user()->isAdmin();
    }
}
