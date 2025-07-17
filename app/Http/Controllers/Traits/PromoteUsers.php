<?php

namespace App\Http\Controllers\Traits;

use App\Models\User;
use App\Enums\PromotedRole;
use Auth;
use Gate;

trait PromoteUsers
{
    protected function promoteUser(User $user, PromotedRole $promotedRole): void
    {

        Gate::authorize('canPromoteUsers', $user);

        if (!$user->isApproved()) {
            throw new \Exception("User is not yet approved.{$user->isApproved()}");
        }

        if (!$user->isActive()) {
            throw new \Exception("User is not actively participant.");
        }

        if ($user->hasPromotedRole($promotedRole)) {
            throw new \Exception("User is already a {$promotedRole->name}.");
        }

        $user->management_level = 'promoted';
        $user->promoted_role = $promotedRole->value;
        $user->promoted_by = Auth::id();

        $user->save();
    }

    protected function removePromotion(User $user): void
    {
        Gate::authorize('canPromoteUsers', $user);

        if (empty($user->promoted_role)) {
            throw new \Exception("User has no promotion.");
        }

        $user->management_level = 'base';
        $user->promoted_role = null;
        $user->promoted_by = Auth::id();

        $user->save();
    }
}
