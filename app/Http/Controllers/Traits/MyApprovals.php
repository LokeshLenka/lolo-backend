<?php

namespace App\Http\Controllers\Traits;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserApproval;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

trait MyApprovals
{
    public function EBMApprovals(Request $request)
    {
        Gate::authorize('EBMOnly', User::class);

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(12, min(100, $perPage));

        $query = User::select(
            'users.id',
            'users.uuid',
            'users.username',
            'users.role',
            'users.is_approved',
            'users.is_active',
            'users.management_level',
            'users.promoted_role'
        )
            ->join('user_approvals', 'users.id', '=', 'user_approvals.user_id')
            ->where('user_approvals.assigned_ebm_id', Auth::id())
            ->whereNotNull('user_approvals.ebm_approved_at');

        // Stats Calculation
        $stats = [
            'total' => (clone $query)->count(),
            'approved' => (clone $query)->where('user_approvals.status', 'ebm_approved')->count(),
            'rejected' => (clone $query)->where('user_approvals.status', 'rejected')->count(),
            'active_members' => (clone $query)->where('users.is_active', true)->count(),
        ];

        // Final Data Retrieval with Pagination
        $approvals = $query->orderBy('user_approvals.ebm_approved_at', 'desc')
            ->with([
                'musicProfile',
                'managementProfile',
                'userApproval:id,user_id,ebm_assigned_at,ebm_approved_at,status,remarks',
                'createdBy:id,uuid,username',
            ])
            ->paginate($perPage);

        // Return pure array/collection data structure
        return [
            'stats' => $stats,
            'data' => $approvals
        ];
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
