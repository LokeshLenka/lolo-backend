<?php

namespace App\Http\Controllers\Traits;

use App\Models\User;
use App\Models\UserApproval;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;


trait ApproveUsers
{
    public function approve(User $user, string $role): void
    {
        Gate::authorize($role, $user);

        $approvalRecord = $user->userApproval()->first();

        if ($user->isApproved() && $approvalRecord && $approvalRecord->getApprovalStatus() === 'approved') {
            throw new \Exception("User is already approved.");
        }

        $user->update([
            'username' => $this->generateUsername(),
            'is_approved' => true,
        ]);

        $user->userApproval()->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        // $user

    }

    public function reject(User $user,string $role): void
    {
        Gate::authorize($role, $user);

        // Refresh user & approval record from DB to avoid stale data
        $user->refresh();
        $approvalRecord = $user->userApproval()->first();

        if ($approvalRecord && $approvalRecord->status === 'rejected') {
            throw new \Exception("User is already rejected.");
        }

        $user->update([
            'username' => null,
            'is_approved' => false,
        ]);

        $user->userApproval()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'approved_by' => Auth::id(),
                'status' => 'rejected',
                'approved_at' => now(),
            ]
        );
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
}
