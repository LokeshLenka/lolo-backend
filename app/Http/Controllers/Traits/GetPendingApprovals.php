<?php

namespace App\Http\Controllers\Traits;

use App\Models\User;
use Auth;
use Illuminate\Support\Facades\Gate;

trait GetPendingApprovals
{
    public function getPendingApprovalsForAdmin()
    {
        return User::where('is_approved', false)
            ->with(['musicProfile', 'managementProfile', 'userApproval'])
            ->orderBy('created_at', 'asc')
            ->simplePaginate(20);
    }

    public function getPendingApprovalsForEBM()
    {
        Gate::authorize('ValidEBM', User::class);

        return User::select([
            'users.id',
            'users.uuid',
            'users.username',
            'users.role',
            'users.is_approved',
            'users.is_active',
            'users.management_level',
            'users.promoted_role',
        ])
            ->join('user_approvals', 'users.id', '=', 'user_approvals.user_id')
            ->where('users.is_approved', false)
            ->where('user_approvals.assigned_ebm_id', Auth::id())
            ->whereNull('user_approvals.ebm_approved_at')
            ->orderBy('users.created_at', 'desc')
            ->with([
                'musicProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role',
                'managementProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role',
                'userApproval:id,user_id,ebm_assigned_at,ebm_approved_at,status',
                'createdBy:id,uuid,username',
            ])
            ->simplePaginate(20);
    }


    public function getPendingApprovalsForMemberShipHead()
    {
        return User::where('is_approved', false)
            ->whereHas('userApproval', function ($query) {
                $query->where('assigned_membership_head_id', Auth::id())
                    ->whereNotNull('ebm_approved_at')
                    ->whereNull('membership_approved_at');
            })
            ->with([
                'musicProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role',
                'managementProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role',
                'userApproval:id,user_id,ebm_assigned_at,ebm_approved_at,status',
                'createdBy:id,uuid,username',
            ])
            ->orderBy('created_at', 'asc')
            ->simplePaginate(20);
    }
}
