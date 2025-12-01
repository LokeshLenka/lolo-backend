<?php

namespace App\Http\Controllers;

use App\Enums\ManagementCategories;
use App\Enums\ManagementLevel;
use App\Enums\PromotedRole;
use App\Enums\UserRoles;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Models\UserApproval;
use App\Services\AdminService;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class AdminController extends Controller
{
    public function __construct(private AdminService $adminService) {}

    public function approve(User $user, Request $request)
    {
        try {
            $validated = $request->validate(
                ['remarks' => 'string | required | min:10 | max:255']
            );

            // Let service handle approval logic and policy checks
            $this->adminService->approveUser($user, $validated['remarks']);
            return response()->json([
                'message' => 'User approved successfully.',
                'Generated username' => $user->getUserName(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    public function reject(User $user, Request $request)
    {
        try {
            $validated = $request->validate(
                ['remarks' => 'string | required | min:10 | max:255']
            );
            // Let service handle approval logic and policy checks
            $this->adminService->rejectUser($user, $validated['remarks']);
            return response()->json(['message' => 'User rejected successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    public function clearLock(User $user)
    {
        try {
            $this->adminService->clearAccountLock($user);
            return response()->json(['message' => 'User unlocked successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    public function promoteUser(string $role, User $user): JsonResponse
    {
        $map = [
            'ebm' => PromotedRole::EXECUTIVE_BODY_MEMBER,
            'credit-manager' => PromotedRole::CREDIT_MANAGER,
            'membership-head' => PromotedRole::MEMBERSHIP_HEAD,
        ];

        if (! isset($map[$role])) {
            return response()->json(['error' => 'Invalid role'], 400);
        }

        $this->adminService->promote($user, $map[$role]);

        return response()->json([
            'message' => 'User promoted successfully.'
        ]);
    }

    public function dePromote(User $user): JsonResponse
    {
        $this->adminService->dePromoteUser($user);

        return response()->json([
            'message' => 'User de-promoted successfully.',
        ]);
    }

    /**
     * Helper function to get pending approvals
     *
     * @param int $limit
     * @return Collection
     */
    public function getPendingApprovalsForAdmin(?int $limit = 20) {}


    // public function listRoleUsersByDomain(string $role, string $domain): JsonResponse
    // {
    //     $users = $this->adminService->getUsersByRoleAndDomain($role, $domain);

    //     return response()->json($users);
    // }

    // public function getRoleUsersByDomain(string $role, string $domain, User $user): JsonResponse
    // {
    //     $userDetails = $this->adminService->getUserDetailsByRoleAndDomain($user, $role, $domain);

    //     return response()->json($userDetails);
    // }

    // public function createUserWithSpecifiedRoleInDomain(Request $request, string $role, string $domain): JsonResponse
    // {
    //     $user = $this->adminService->createUserWithRoleAndDomain(
    //         $request->validated(),
    //         $role,
    //         $domain
    //     );

    //     return response()->json([
    //         'message' => 'User created successfully.',
    //         'user' => $user,
    //     ]);
    // }

    // public function deleteUserWithSpecifiedRole(string $role, User $user): JsonResponse
    // {
    //     $this->adminService->deleteUserWithRole($user, $role);

    //     return response()->json([
    //         'message' => 'User deleted successfully.'
    //     ]);
    // }

    public function getDashboardStatisticsForAdmin(): JsonResponse
    {
        Gate::authorize('adminOnly', User::class);
        $authId = Auth::id();
        try {
            $stats = [
                'total_active_users' => User::where('is_active', true)->where('is_approved', true)->count(),
                'total_in_active_users' => User::where('is_active', false)->where('is_approved', true)->count(),
                'total_approved_users' => User::where('is_approved', true)->count(),
                'total_pending_approvals' => User::where('is_approved', false)->count(),
                'total_promoted_users' => User::where('management_level', ManagementLevel::Promoted)->count(),
                'total_management_users' => User::where('role', UserRoles::ROLE_MANAGEMENT)->count(),
                'total_music_users' => User::where('role', UserRoles::ROLE_MUSIC)->count(),

                'total_event_organizers' => User::whereHas('managementProfile', function ($query) {
                    $query->where('sub_role', ManagementCategories::EVENT_ORGANIZER);
                })->count(),

                'total_event_planners' => User::whereHas('managementProfile', function ($query) {
                    $query->where('sub_role', ManagementCategories::EVENT_PLANNER);
                })->count(),

                'total_social_media_handlers' => User::whereHas('managementProfile', function ($query) {
                    $query->where('sub_role', ManagementCategories::SOCIAL_MEDIA_HANDLER);
                })->count(),

                'total_marketing_coordinators' => User::whereHas('managementProfile', function ($query) {
                    $query->where('sub_role', ManagementCategories::MARKETING_COORDINATOR);
                })->count(),

                'total_video_editors' => User::whereHas('managementProfile', function ($query) {
                    $query->where('sub_role', ManagementCategories::VIDEO_EDITOR);
                })->count(),


                // 'assigned_user_count' => UserApproval::whereNotNull('')->count(),
                // Pending approvals assigned to this admin
                'pending_approvals' => UserApproval::where('assigned_membership_head_id', $authId)->whereNull('membership_head_approved_at')->count(),
                // Total approvals done by any membership head
                'total_approvals' => UserApproval::whereNotNull('membership_head_approved_at')->count(),
                // Approval trend for this admin
                'approval_trend' => $this->getApprovalTrend(),
                // Promoted users by this admin (uncomment in production)
                // 'promoted_users' => User::where('promoted_by', $authId)->count(),
                // Total users by promoted roles
                'total_ebms' => User::where('promoted_role', PromotedRole::EXECUTIVE_BODY_MEMBER)->count(),
                'total_memberships' => User::where('promoted_role', PromotedRole::MEMBERSHIP_HEAD)->count(),
                'total_credit_managers' => User::where('promoted_role', PromotedRole::CREDIT_MANAGER)->count(),
            ];
        } catch (\Exception $e) {
            $this->logError('dashboard_statistics_retrieval_failed', $e, [
                'admin_id' => $authId
            ]);
            return $this->respondError('Failed to retrieve dashboard statistics', 500, $e->getMessage());
        }
        if (empty($stats)) {
            return $this->respondError('No statistics available', 404);
        }
        return $this->respondSuccess(
            $stats,
            'Dashboard statistics retrieved successfully',
            200
        );
    }

    /**
     * Get approval trend for the last 7 days
     *
     * @return array
     */
    private function getApprovalTrend(): array
    {
        $trend = [];
        $startDate = Carbon::now()->subDays(6);

        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $count = UserApproval::where('assigned_membership_head_id', Auth::id())
                ->whereDate('membership_head_approved_at', $date)
                ->count();

            $trend[] = [
                'date' => $date->format('Y-m-d'),
                'count' => $count
            ];
        }

        return $trend;
    }
}
