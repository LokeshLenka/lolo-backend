<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Auth;

class EventRegistrationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EventRegistration $eventRegistration): bool
    {
        return $user->id === $eventRegistration->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return Auth::check();
    }

    /**
     * Determine whether the user can update the model.
     */
        public function update(User $user, EventRegistration $eventRegistration): bool
        {
            return $user->isAdmin();
        }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EventRegistration $eventRegistration): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EventRegistration $eventRegistration): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EventRegistration $eventRegistration): bool
    {
        return $user->isAdmin();
    }

    public function viewClubRegistrations(User $user)
    {
        return $user->isClubMember() && $user->isApproved();
    }

    public function viewMusicRegistrations(User $user)
    {
        return $user->isMusicMember() && $user->isApproved();
    }


    public function register(User $user, Event $event, $policyClass)
    {
        return match ($event->type) {
            'club' => $user->isApproved() && $user->isClubMember()
                ? Response::allow()
                : Response::deny('You must be an approved club member to register.'),

            'members' => $user->isApproved() && $user->isMember()
                ? Response::allow()
                : Response::deny('You must be an approved member to register.'),

            // 'all' =>
            default => Response::deny('Invalid event type.')
        };
    }
}
