<?php

namespace App\Policies;

use App\Models\Credit;
use App\Models\User;

class CreditPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canManageCredits();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(
        User $user,
        //  Credit $credit
    ): bool {
        return $user->canManageCredits();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->canManageCredits();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Credit $credit): bool
    {
        return $user->canManageCredits();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Credit $credit): bool
    {
        return $user->canManageCredits();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Credit $credit): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Credit $credit): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view their models
     */
    public function getMyCredits(User $user): bool
    {
        return $user->hasEligibleCreditRole() && $user->is_approved;
    }
}
