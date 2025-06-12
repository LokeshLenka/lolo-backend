<?php

namespace App\Policies;

use App\Enums\PromotedRole;
use App\Models\User;
use App\Enums\UserRoles;
use Auth;
use Illuminate\Validation\Rules\Enum;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->canApproveUsers();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->canApproveUsers();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     *
     */
    public function adminOnly(User $authUser): bool
    {
        return $authUser->isAdmin(); // Assumes isAdmin() returns true if role is admin
    }

    public function membershipHeadOnly(User $authUser): bool
    {
        return $authUser->isMembershipHead();
    }

    /**
     * Determine if the user can approve/reject another user.
     */
    public function managebyadmin(User $authUser, User $targetUser): bool
    {
        return $authUser->id !== $targetUser->id && $authUser->canApproveUsers();
    }
    /**
     * Determine if the user can approve/reject another user(expect membershiphead).
     */
    public function managebymh(User $authUser, User $targetUser): bool
    {
        return $authUser->id !== $targetUser->id &&
            $authUser->canApproveUsers() &&
            $targetUser->promoted_role !== PromotedRole::MEMBERSHIP_HEAD;
    }


    /**
     * Determine if the user can approve another user.
     */
    // public function reject(User $authUser, User $user): bool
    // {
    //     return $authUser->id !== $user->id && $authUser->canApproveUsers();
    // }

    /**
     * Determine if the user can clear another user's account lock.
     */
    public function clearlock(User $authUser, User $user): bool
    {
        return $authUser->id !== $user->id && $authUser->isAdmin();
    }
}
