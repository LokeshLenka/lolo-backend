<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;
use App\Http\Requests\StoreEventRegistration;
use App\Services\EventRegistrationService;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Enums\EventType;
use App\Http\Requests\UpdateEventRegistration;
use App\Models\Credit;
use Carbon\Carbon;

class EventRegistrationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canViewRegistrations();
    }

    public function viewAll(User $user): bool
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
        return Auth::check() && Auth::user()->isApproved();
    }

    /**
     * Determine whether the user can update the model.
     */
    private function update(User $user, EventRegistration $eventRegistration): Response
    {
        if (!$user->isAdmin()) {
            return Response::deny('Only admins can update the regstration');
        }

        if (!$eventRegistration) {
            return Response::deny('Event not found.');
        }

        return Response::allow();
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
    public function restore(User $user, EventRegistration $eventRegistration): Response
    {
        if (!$user->isAdmin()) {
            return Response::deny('Only admins can update the regstration');
        }

        if (!($eventRegistration)) {
            return   Response::deny('Event not found.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EventRegistration $eventRegistration): bool
    {
        return $user->isAdmin();
    }

    public function updateClubRegistration(User $user, EventRegistration $eventRegistration)
    {
        return $this->update($user, $eventRegistration);
    }

    public function updateMusicRegistration(User $user, EventRegistration $eventRegistration)
    {
        return $this->update($user, $eventRegistration);
    }

    public function viewClubRegistrations(User $user)
    {
        return $this->checkClubMemberAccess($user);
    }

    public function storeClubRegistrations(User $user,)
    {
        return $this->checkClubMemberAccess($user);
    }

    public function viewMusicRegistrations(User $user)
    {
        return $this->checkMusicMemberAccess($user);
    }

    public function storeMusicRegistrations(User $user)
    {
        return $this->checkMusicMemberAccess($user);
    }


    public function showUserClubRegistration(User $user, EventRegistration $eventRegistration)
    {
        return $this->checkClubMemberAccess($user) && $user->id === $eventRegistration->user_id;
    }

    public function showUserMemberRegistration(User $user, EventRegistration $eventRegistration)
    {
        return $this->checkMusicMemberAccess($user) && $user->id === $eventRegistration->user_id;
    }


    // ------------------ Private Helpers ------------------

    private function checkClubMemberAccess(User $user)
    {
        if (!Auth::check()) {
            return Response::deny('Access denied. You must logged in.');
        }

        if (!$user->isClubMember()) {
            return Response::deny('Access denied. This section is exclusive to approved club members.');
        }

        if (!$user->isApproved()) {
            return Response::deny('Your club membership is pending approval. Please contact the administrator.');
        }

        return Response::allow();
    }

    private function checkMusicMemberAccess(User $user)
    {
        if (!Auth::check()) {
            return Response::deny('Access denied. You must logged in.');
        }
        if (!$user->isMusicMember()) {
            return Response::deny('Access denied. Only approved musical members can access this section.');
        }

        if (!$user->isApproved()) {
            return Response::deny('Your music membership is not yet approved. Please wait or contact support.');
        }

        return Response::allow();
    }

    // public function register(User $user, Event $event, $policyClass)
    // {
    //     return match ($event->type) {
    //         'club' => $user->isApproved() && $user->isClubMember()
    //             ? Response::allow()
    //             : Response::deny('You must be an approved club member to register.'),

    //         'members' => $user->isApproved() && $user->isMember()
    //             ? Response::allow()
    //             : Response::deny('You must be an approved member to register.'),

    //         // 'all' =>
    //         default => Response::deny('Invalid event type.')
    //     };
    // }
}
