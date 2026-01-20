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
        Gate::authorize('EBMOnly', User::class);

        return User::select([
            'id',
            'uuid',
            'username',
            'role',
            'is_approved',
            'is_active',
            'management_level',
            'promoted_role',
            'created_at',
        ])
            ->where('is_approved', false)
            ->whereHas('userApproval', function ($query) {
                $query->where('assigned_ebm_id', Auth::id())
                    ->whereNull('ebm_approved_at');
            })
            ->with([
                'musicProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role',
                'managementProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role',
                'userApproval:id,uuid,user_id,ebm_assigned_at,ebm_approved_at,status',
                'createdBy:id,uuid,username',
            ])
            ->orderBy('created_at', 'desc')
            ->simplePaginate(20);
    }



    public function getPendingApprovalsForMemberShipHead()
    {
        Gate::authorize('memberShipHeadOnly', User::class);

        return User::where('is_approved', false)
            ->whereHas('userApproval', function ($query) {
                $query->where('assigned_membership_head_id', Auth::id())
                    ->whereNotNull('membership_head_assigned_at')
                    ->whereNull('membership_head_approved_at');
            })
            ->with([
                'musicProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role',
                'managementProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role',
                'userApproval:id,uuid,user_id,ebm_assigned_at,ebm_approved_at,membership_head_assigned_at,membership_head_approved_at,status',
                'createdBy:id,uuid,username',
            ])
            ->orderBy('created_at', 'asc')
            ->simplePaginate(20);
    }
}
