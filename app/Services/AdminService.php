<?php

namespace App\Services;

use App\Models\User;

class AdminService
{

    public function approveUser(User $user): void
    {
        $this->userExists($user);

        if ($user->isApproved()) {
            throw new \Exception("User is already approved.");
        }

        $generatedUserName = $this->generateUsername();

        $user->update([
            'username' => $generatedUserName,
            'is_approved' => true,
        ]);
    }

    public function generateUsername(): string
    {
        $year = now()->format('y');
        $middle = '0707';

        $lastUser = User::where('username', 'like', "{$year}{$middle}%")
            ->orderByDesc('username')
            ->first();

        $nextSequence = $lastUser
            ? str_pad(((int)substr($lastUser->username, -4)) + 1, 4, '0', STR_PAD_LEFT)
            : '0001';

        return "{$year}{$middle}{$nextSequence}";
    }


    public function clearAccountLock(User $user): void
    {

        // check if user exists
        $this->userExists($user);

        // check the account is locked
        if (!$user->isAccountLocked()) {
            throw new \Exception("Account already unlocked!.");
        }

        // unlocks the account
        $user->resetFailedAttempts();
    }


    //currently not working,it automatically throws a exception message "No query results for model [App\\Models\\User] userid"
    // for furthur security,optinal
    public function userExists(User $user): void
    {
        if (!User::find($user->id)) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("User not found!");
        }
    }
}
