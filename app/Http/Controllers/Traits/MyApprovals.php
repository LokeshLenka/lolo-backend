<?php

namespace App\Http\Controllers\Traits;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserApproval;
use Auth;
use Illuminate\Support\Facades\Gate;

trait MyApprovals
{
    public function EBMApprovals()
    {
        Gate::authorize('EBMOnly', User::class);

        return User::select(
            'users.id',
            'users.uuid',
            'users.username',
            'users.role',
            'users.is_approved',
            'users.is_active',
            'users.management_level',
            'users.promoted_role',
        )
            ->join('user_approvals', 'users.id', '=', 'user_approvals.user_id')
            ->where('user_approvals.assigned_ebm_id', Auth::id())
            ->whereNotNull('user_approvals.ebm_approved_at')
            ->orderBy('user_approvals.ebm_approved_at', 'desc')
            ->with([
                'musicProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role',
                'managementProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role',
                'userApproval:id,user_id,ebm_assigned_at,ebm_approved_at,status',
                'createdBy:id,uuid,username',
            ])
            ->simplePaginate(20);
    }

    public function MemberShipHeadApprovals()
    {
        Gate::authorize('memberShipHeadOnly', User::class);

        return User::select(
            'users.id',
            'users.uuid',
            'users.username',
            'users.role',
            'users.is_approved',
            'users.is_active',
            'users.management_level',
            'users.promoted_role',
        )
            ->join('user_approvals', 'users.id', '=', 'user_approvals.user_id')
            ->where('user_approvals.assigned_membership_head_id', Auth::id())
            ->whereNotNull('user_approvals.membership_head_approved_at')
            ->orderBy('user_approvals.membership_head_approved_at', 'desc')
            ->with([
                'musicProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role',
                'managementProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role',
                'userApproval:id,user_id,ebm_assigned_at,ebm_approved_at,status',
                'createdBy:id,uuid,username',
            ])
            ->simplePaginate(20);
    }
}
