<?php

namespace App\Policies;

use App\Models\Credit;
use App\Models\User;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Auth\Access\Response;

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
    public function createCredit(User $user, Event $event): Response
    {
        if ($user->canManageCredits()) {
            return Response::deny($user->getUserRole() . 'not have permissions to do this.');
        }

        if (Carbon::now()->lessThanOrEqualTo($event->end_date)) {
            return Response::deny('Event not yet completed to update credits');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function updateCredit(User $user, Event $event)
    {
        if ($user->canManageCredits()) {
            return Response::deny($user->getUserRole() . 'not have permissions to do this.');
        }

        if (Carbon::now()->lessThanOrEqualTo($event->end_date)) {
            return Response::deny('Event not yet completed to update credits');
        }

        return Response::allow();
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
    public function getUserCredits(User $user): bool
    {
        return $user->hasEligibleCreditRole() && $user->isApproved();
    }

    public function showUserCreditsDetails(User $user, Credit $credit): bool
    {
        return $user->id === $credit->user_id;
    }
}
