<?php

namespace App\Http\Controllers\Traits;

use App\Models\User;
use App\Enums\PromotedRole;

trait PromoteUsers
{
    protected function promoteUser(User $user, PromotedRole $promotedRole): void
    {
        if ($user->hasPromotedRole($promotedRole)) {
            throw new \Exception("User is already a {$promotedRole->name}.");
        }

        $user->management_level = 'promoted';
        $user->promoted_role = $promotedRole->value;

        $user->save();
    }
}
