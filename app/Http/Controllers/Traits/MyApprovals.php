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

        // 1. Setup Pagination & Inputs
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(10, min(100, $perPage));

        $status = $request->query('status');
        $role = $request->query('role');
        $search = $request->query('search');
        $ebmId = Auth::id();

        // 2. Base Query: All users assigned to this EBM where a decision was made
        // We use 'select' to avoid column collisions during the join
        $baseQuery = User::select(
            'users.id',
            'users.uuid',
            'users.username',
            'users.email',
            'users.role',
            'users.is_approved',
            'users.is_active',
            'users.management_level',
            'users.promoted_role',
            'users.created_at'
        )
            ->join('user_approvals', 'users.id', '=', 'user_approvals.user_id')
            ->where('user_approvals.assigned_ebm_id', $ebmId)
            ->whereNotNull('user_approvals.ebm_approved_at'); // Only completed approvals

        // 3. Stats Calculation
        // These stats represent the EBM's *Total History*, regardless of current search filters
        $statsQuery = clone $baseQuery;

        // We need a separate query for active members because the base query might strictly look at approval table
        $activeMembersCount = User::where('is_active', true)
            ->whereHas(
                'userApproval',
                fn($q) =>
                $q->where('assigned_ebm_id', $ebmId)
                    ->where('status', 'ebm_approved')
            )->count();

        $stats = [
            'total'    => (clone $statsQuery)->count(),
            'approved' => (clone $statsQuery)->where('user_approvals.status', 'ebm_approved')->count(),
            'rejected' => (clone $statsQuery)->where('user_approvals.status', 'rejected')->count(),
            'active_members' => $activeMembersCount,
        ];

        // 4. Apply Filters to the Data Query

        // Status Filter
        if ($status) {
            $baseQuery->where('user_approvals.status', $status);
        }

        // Role Filter
        if ($role) {
            $baseQuery->where('users.role', $role);
        }

        // Search Filter (Requires complex logic because of Join)
        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('users.username', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhereHas('musicProfile', function ($subQ) use ($search) {
                        $subQ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('reg_num', 'like', "%{$search}%");
                    })
                    ->orWhereHas('managementProfile', function ($subQ) use ($search) {
                        $subQ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('reg_num', 'like', "%{$search}%");
                    });
            });
        }

        // 5. Retrieve Data with Eager Loading
        $approvals = $baseQuery
            ->orderBy('user_approvals.ebm_approved_at', 'desc')
            ->with([
                'musicProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role,phone_no,experience,passion,instrument_avail',
                'managementProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role,phone_no,experience,interest_towards_lolo',
                'userApproval:id,user_id,assigned_ebm_id,ebm_assigned_at,ebm_approved_at,status,remarks',
                'createdBy:id,uuid,username',
            ])
            ->paginate($perPage);

        // 6. Return Data Structure
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
