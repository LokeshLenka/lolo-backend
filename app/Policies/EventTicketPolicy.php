<?php

namespace App\Policies;

use App\Models\EventTicket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventTicketPolicy
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
    public function view(User $user, EventTicket $eventTicket): bool
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
    public function update(User $user): bool
    {
        return $user->isClubMember();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EventTicket $eventTicket): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EventTicket $eventTicket): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EventTicket $eventTicket): bool
    {
        return false;
    }

    public function copyRecords(User $user): bool
    {
        return $user->isAdmin();
    }
}
