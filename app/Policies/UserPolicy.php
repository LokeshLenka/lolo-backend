<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

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
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine if the user can approve/reject another user.
     */
    public function managebyadmin(User $authUser, User $user): bool
    {
        return $authUser->id !== $user->id && $authUser->canApproveUsers();
    }

    /**
     * Determine if the user can approve/reject another user(expect membershiphead).
     */
    public function managebymh(User $authUser, User $user): bool
    {
        return $authUser->id !== $user->id && $authUser->canApproveUsers() && $user->role !== 'mh';
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
