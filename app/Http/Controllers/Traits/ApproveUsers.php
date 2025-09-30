<?php

namespace App\Http\Controllers\Traits;

use App\Enums\UserApprovalStatus;
use App\Enums\PromotedRole;
use App\Enums\UserRoles;
use App\Models\User;
use App\Models\UserApproval;
use Exception;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Trait for handling user approval operations
 */
trait ApproveUsers
{
    /**
     * Approve user by EBM
     */
    public function approveByEBM(User $user, string $policyAbility, string $remarks): void
    {
        Gate::authorize($policyAbility, $user);

        $user->refresh();
        $approval = $user->userApproval()->first();
        $approval->refresh();


        if (!$approval) {
            throw new \Exception("Approval record not found.");
        }

        if ($approval->ebm_approved_at !== null && $approval->status === UserApprovalStatus::EBM_APPROVED) {
            throw new \Exception("Already approved by EBM.");
        }

        if ($approval->assigned_ebm_id !== Auth::id()) {
            throw new \Exception("You are not assigned as the EBM for this user.");
        }

        DB::transaction(function () use ($approval, $remarks) {
            // EBM approval - do NOT generate username yet, wait for membership/admin approval

            $authUserName = Auth::user()->getUserName() ?? null;

            $approval->update([
                'status' => UserApprovalStatus::EBM_APPROVED->value,
                'ebm_approved_at' => Carbon::now(),
                'remarks' => $approval->remarks . "By EBM({$authUserName}) - \n" . $remarks . "\n",
            ]);
        });
    }

    /**
     * Approve user by Membership Head
     */
    public function approveByMemberShipHead(User $user, string $policyAbility, string $remarks): mixed
    {
        Gate::authorize($policyAbility, $user);

        $user->refresh();
        $approval = $user->userApproval()->first();

        if (!$approval) {
            throw new \Exception("Approval record not found.");
        }

        if ($user->isApproved()) {
            throw new \Exception("User is already approved.");
        }

        if ($approval->membership_head_approved_at !== null) {
            throw new \Exception("Already approved by Membership Head.");
        }

        if ($approval->ebm_approved_at === null) {
            throw new \Exception("Not yet approved by EBM");
        }

        if ($approval->assigned_membership_head_id !== Auth::id()) {
            throw new \Exception("You are not assigned as the Membership Head for this user.");
        }

        return DB::transaction(function () use ($user, $approval, $remarks) {

            // Generate username and approve user when membership head approves
            if ($user->username) {
                $user->update([
                    'is_active' => true,
                    'is_approved' => true,
                ]);
            } else {
                $user->update([
                    'username' => $this->generateUsername(),
                    'is_approved' => true,
                    'is_active' => true,
                ]);
            }

            $authUserName = Auth::user()->getUserName() ?? null;
            $approval->update([
                'status' => UserApprovalStatus::MEMBERSHIP_APPROVED->value,
                'membership_head_approved_at' => Carbon::now(),
                'approved_at' => Carbon::now(),
                'remarks' => $approval->remarks . "By Membership-Head({$authUserName}) - \n" . $remarks . "\n",
            ]);
        });
    }

    /**
     * Approve user by Admin
     */
    public function approveByAdmin(User $user, string $policyAbility, string $remarks): mixed
    {
        Gate::authorize($policyAbility, $user);

        $user->refresh();
        $approval = $user->userApproval()->first();

        if (!$approval) {
            throw new \Exception("Approval record not found.");
        }

        if ($user->isApproved()) {
            throw new \Exception("User is already approved.");
        }

        return DB::transaction(function () use ($user, $approval, $remarks) {
            // Admin can directly approve - generate username and approve user

            if ($user->username) {
                $user->update([
                    'is_active' => true,
                    'is_approved' => true,
                ]);
            } else {
                $user->update([
                    'username' => $this->generateUsername(),
                    'is_approved' => true,
                    'is_active' => true,
                ]);
            }

            $authUserName = Auth::user()->getUserName() ?? null;

            $approval->update([
                'status' => UserApprovalStatus::ADMIN_APPROVED->value,
                'approved_at' => Carbon::now(),
                'remarks' => $approval->remarks . "By Admin({$authUserName}) - \n" . $remarks . "\n",

            ]);
        });
    }

    /**
     * Reject user by EBM
     */
    public function rejectByEBM(User $user, string $policyAbility, string $remarks)
    {
        Gate::authorize($policyAbility, $user, User::class);

        $user->refresh();
        $approval = $user->userApproval()->first();

        if (!$approval) {
            throw new \Exception("Approval record not found.");
        }

        if ($approval->status === UserApprovalStatus::REJECTED->value) {
            throw new \Exception("User is already rejected.");
        }

        if ($approval->status === UserApprovalStatus::MEMBERSHIP_APPROVED->value) {
            throw new \Exception('The user is already approved by higher authority [' . PromotedRole::MEMBERSHIP_HEAD->value . ']');
        }
        if ($approval->status === UserApprovalStatus::ADMIN_APPROVED->value) {
            throw new \Exception('The user is already approved by higher authority [' . UserRoles::ROLE_ADMIN->value . ']');
        }

        if ($approval->assigned_ebm_id !== Auth::id()) {
            throw new \Exception("You are not assigned as the EBM for this user.");
        }

        if ($user->hasPromoted()) {
            throw new \Exception("You can't reject a Promoted user - {$user->promotted_role}.");
        }

        return $this->reject($user, $approval, PromotedRole::EXECUTIVE_BODY_MEMBER->value, $remarks);
    }

    /**
     * Reject user by Membership Head
     */
    public function rejectByMemberShipHead(User $user, string $policyAbility, string $remarks): mixed
    {
        Gate::authorize($policyAbility, $user);

        $user->refresh();
        $approval = $user->userApproval()->first();

        if (!$approval) {
            throw new \Exception("Approval record not found.");
        }

        if ($approval->status === UserApprovalStatus::REJECTED->value) {
            throw new \Exception("User is already rejected.");
        }

        if ($approval->assigned_membership_head_id !== Auth::id()) {
            throw new \Exception("You are not assigned as the Membership Head for this user.");
        }

        return $this->reject($user, $approval, PromotedRole::MEMBERSHIP_HEAD->value, $remarks);
    }

    /**
     * Reject user by Admin
     */
    public function rejectByAdmin(User $user, string $policyAbility, string $remarks): mixed
    {
        Gate::authorize($policyAbility, $user);

        $user->refresh();
        $approval = $user->userApproval()->first();

        if (!$approval) {
            throw new \Exception("Approval record not found.");
        }

        if ($approval->status === UserApprovalStatus::REJECTED->value) {
            throw new \Exception("User is already rejected.");
        }

        return $this->reject($user, $approval, UserRoles::ROLE_ADMIN->value, $remarks);
    }

    /**
     * Generate unique username
     */
    public function generateUsername(): string
    {
        $year = Carbon::now()->format('y');
        $middle = '0707';

        $lastUser = User::where('username', 'like', "{$year}{$middle}%")
            ->orderByDesc('username')
            ->first();

        $nextSequence = $lastUser
            ? str_pad(((int)substr($lastUser->username, -4)) + 1, 4, '0', STR_PAD_LEFT)
            : '0001';

        // checking the sequence reached the max limit
        if ((int)$nextSequence <= 9999) {
            return "{$year}{$middle}{$nextSequence}";
        } else {
            return throw new Exception('Maximum registration limit reached.');
        }
    }

    /**
     * Handle user rejection
     */
    private function reject(User $user, UserApproval $approval, string $role, ?string $remarks = null)
    {
        return DB::transaction(function () use ($user, $approval, $role, $remarks) {

            // Clear username and set user as not approved
            $user->update([
                // 'username' => null,
                'is_active' => false,
                'is_approved' => false,
            ]);

            $authUserName = Auth::user()->getUserName() ?? null;

            // Update approval record as rejected
            $approval->update([
                'status' => UserApprovalStatus::REJECTED->value,
                'remarks' => $approval->remarks . "By {$role}({$authUserName}) - \n" . $remarks . "\n",
                // Note: No specific rejected_at field in your table, using approved_at for tracking
                'approved_at' => Carbon::now(),
            ]);
        });
    }
}
