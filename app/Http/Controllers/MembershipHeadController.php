<?php

namespace App\Http\Controllers;

use App\Enums\PromotedRole;
use App\Services\MembershipHeadService;
use App\Models\User;
use App\Http\Requests\RegisterRequest;
use App\Models\UserApproval;
use Exception;
use Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Class MembershipHeadController
 *
 * Handles all Membership Head related actions such as:
 * - Approving and rejecting users after EBM approval
 * - Retrieving pending and approved users for the authenticated Membership Head
 * - Promoting and de-promoting users to/from specific roles
 * - Fetching dashboard statistics for Membership Head
 *
 * Dependencies:
 * - MembershipHeadService: Handles business logic for Membership Head actions
 *
 * Endpoints:
 * - approve(Request $request, User $user): Approve a user with remarks
 * - reject(User $user, Request $request): Reject a user with remarks
 * - getMyPendingApprovals(): Get users pending Membership Head approval
 * - getMyApprovals(): Get users approved by Membership Head
 * - promoteUser(string $role, User $user): Promote a user to a specific role (except membership-head)
 * - dePromote(User $user): De-promote a user from their current role
 * - getDashboardStatistics(): Get dashboard statistics for Membership Head
 *
 * Authorization:
 * - Uses Laravel Gates to restrict actions to Membership Head role where appropriate
 *
 * Error Handling:
 * - Returns JSON error responses with appropriate HTTP status codes and messages
 *
 * @package App\Http\Controllers
 */
class MembershipHeadController extends Controller
{
    public function __construct(private MembershipHeadService $membershipService) {}


    /**
     * Approve a user by Membership Head after EBM approval
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function approve(Request $request, User $user)
    {
        try {

            $validated = $request->validate([
                'remarks' => 'required|string|min:10|max:255'
            ]);

            // Let service handle approval logic and policy checks
            $this->membershipService->approveUser($user, $validated['remarks']);
            return $this->respondSuccess(
                [
                    'generated_username' => $user->getUserName(),
                    'approved_by' => Auth::id(),
                ],
                'User approved successfully',
                200
            );
        } catch (\Throwable $e) {
            return $this->respondError('User approval failed', $e->getCode() ?: 403, $e->getMessage());
        }
    }

    /**
     * Reject a user by Membership Head
     *
     * @param User $user
     * @param Request $request
     * @return JsonResponse
     */
    public function reject(User $user, Request $request)
    {
        try {
            $validated = $request->validate([
                'remarks' => 'required|string|min:10|max:255'
            ]);

            // Let service handle approval logic and policy checks
            $this->membershipService->rejectUser($user, $validated['remarks']);
            return $this->respondSuccess(null, 'User rejected successfully', 200);
        } catch (\Throwable $e) {
            return $this->respondError('User rejection failed', $e->getCode() ?: 403, $e->getMessage());
        }
    }


    /**
     * Get pending approvals for the authenticated Membership-Head
     *
     * @return AnonymousResourceCollection
     */
    public function getMyPendingApprovals()
    {
        try {
            return $this->respondSuccess(
                $this->membershipService->getPendingApprovals(),
                'Pending approvals retrieved successfully',
                200
            );
        } catch (Exception $e) {
            $this->logError('pending_approvals_retrieval_failed', $e, [
                'ebm_id' => Auth::id()
            ]);
            return $this->respondError('Failed to retrieve pending approvals', 500, $e->getMessage());
        }
    }

    /**
     * Get approved users by the authenticated Membership-Head
     *
     * @return AnonymousResourceCollection
     */
    public function getMyApprovals()
    {
        try {
            return $this->respondSuccess(
                $this->membershipService->getApprovals(),
                'Approvals retrieved successfully',
                200
            );
        } catch (Exception $e) {
            $this->logError('my_approvals_retrieval_failed', $e, [
                'ebm_id' => Auth::id()
            ]);
            return $this->respondError('Failed to retrieve the data.', 500, $e->getMessage());
        }
    }

    /**
     * Promote a user to a specific role
     *
     * @param string $role
     * @param User $user
     * @return JsonResponse
     */
    public function promoteUser(string $role, User $user): JsonResponse
    {
        $map = [
            'ebm' => PromotedRole::EXECUTIVE_BODY_MEMBER,
            'credit-manager' => PromotedRole::CREDIT_MANAGER,
        ];

        if ($role === 'membership-head') {
            throw new \Exception('A Membership-Head is unauthorized to promote a user as membership-head');
        }

        if (! isset($map[$role])) {
            return $this->respondError('Invalid role', 400);
        }

        $this->membershipService->promote($user, $map[$role]);

        return $this->respondSuccess(null, 'User promoted successfully', 200);
    }

    /**
     * De-promote a user from their current role
     *
     * @param User $user
     * @return JsonResponse
     */
    public function dePromote(User $user): JsonResponse
    {
        $this->membershipService->dePromoteUser($user);

        return $this->respondSuccess(null, 'User de-promoted successfully', 200);
    }

    /**
     * Get dashboard statistics for the Membership Head
     *
     * @return JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function getDashboardStatistics()
    {
        Gate::authorize('membershipHeadOnly', User::class);

        $authId = Auth::id();

        try {
            $stats = [
                'assigned_user_count' => UserApproval::where('assigned_membership_head_id', $authId)->count(),
                'pending_approvals' => UserApproval::where('assigned_membership_head_id', $authId)->count(),
                'total_approvals' => UserApproval::whereNotNull('membership_head_approved_at')->count(),
                // 'promoted_users' => User::where('promoted_by', $authId)->count(), //Uncomment this line at production
                'total_users' => User::where('is_active', true)->where('is_approved')->count(),
                'total_ebms' => User::where('promoted_role', PromotedRole::EXECUTIVE_BODY_MEMBER)->count(),
                'total_memberships' => User::where('promoted_role', PromotedRole::MEMBERSHIP_HEAD)->count(),
                'total_credit_managers' => User::where('promoted_role', PromotedRole::CREDIT_MANAGER)->count(),
            ];
        } catch (Exception $e) {
            $this->logError('dashboard_statistics_retrieval_failed', $e, [
                'membership_head_id' => $authId
            ]);
            return $this->respondError('Failed to retrieve dashboard statistics', 500, $e->getMessage());
        }

        // Check if stats are empty
        if (empty($stats)) {
            return $this->respondError('No statistics available', 404);
        }

        return $this->respondSuccess(
            $stats,
            'Dashboard statistics retrieved successfully',
            200
        );
    }
}
