<?php

namespace App\Policies;

use App\Models\Credit;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * 🛡️ CreditPolicy
 *
 * This policy class governs what actions users can perform on Credit resources.
 * It includes checks for viewing, creating, updating, deleting, and restoring credit records.
 *
 * Permissions are primarily determined based on user roles and status.
 */
class CreditPolicy
{
    /**
     * ✅ Allow viewing all credit models.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->canManageCredits();
    }

    /**
     * ✅ Allow viewing a specific credit model.
     *
     * Note: $credit is not needed here due to logic simplicity.
     *
     * @param User $user
     * @return bool
     */
    public function view(User $user): bool
    {
        return $user->canManageCredits();
    }

    /**
     * ✏️ Allow credit creation.
     *
     * Denies access with a role-based friendly message if unauthorized.
     *
     * @param User $user
     * @return Response
     */
    public function createCredit(User $user): Response
    {
        if (!$user->canManageCredits()) {
            return Response::deny("Oops! As a '{$user->promoted_role->value}', you don't have permission to create credits.");
        }

        return Response::allow();
    }

    /**
     * 🛠️ Allow updating a credit record.
     *
     * Denies access with a role-based friendly message if unauthorized.
     *
     * @param User $user
     * @return Response
     */
    public function updateCredit(User $user): Response
    {
        if (!$user->canManageCredits()) {
            return Response::deny("Sorry! Your role '{$user->promoted_role->value}' isn't allowed to update credits.");
        }

        return Response::allow();
    }

    /**
     * ❌ Allow deleting a credit record.
     *
     * Denies access with a general message if unauthorized.
     *
     * @param User $user
     * @return Response
     */
    public function deleteCredit(User $user): Response
    {
        return $user->canManageCredits()
            ? Response::allow()
            : Response::deny("You are not authorized to delete credit records.");
    }

    /**
     * ♻️ Allow restoring a soft-deleted credit.
     *
     * Only users with admin role can restore.
     *
     * @param User $user
     * @param Credit $credit
     * @return bool
     */
    public function restore(User $user, Credit $credit): bool
    {
        return $user->isAdmin();
    }

    /**
     * 🗑️ Allow force deleting a credit.
     *
     * Only users with admin role can permanently delete.
     *
     * @param User $user
     * @param Credit $credit
     * @return bool
     */
    public function forceDelete(User $user, Credit $credit): bool
    {
        return $user->isAdmin();
    }

    /**
     * 👤 Allow user to view their own credits.
     *
     * Only eligible and approved users can access their credit info.
     *
     * @param User $user
     * @return bool
     */
    public function getUserCredits(User $user): bool
    {
        return $user->isEligibleForEventRegistration() && $user->isApproved();
    }

    /**
     * 🔍 Allow user to view a specific credit's details.
     *
     * Only if the credit belongs to the logged-in user.
     *
     * @param User $user
     * @param Credit $credit
     * @return bool
     */
    public function showUserCreditsDetails(User $user, Credit $credit): bool
    {
        return $user->id === $credit->user_id;
    }
}
