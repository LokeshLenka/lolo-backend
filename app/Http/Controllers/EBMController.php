<?php

namespace App\Http\Controllers;

use App\Enums\UserRoles;
use App\Http\Controllers\Traits\ApproveUsers;
use App\Http\Controllers\Traits\GetMyRegistrations;
use App\Http\Controllers\Traits\GetPendingApprovals;
use App\Http\Controllers\Traits\HandlesUserProfiles;
use App\Http\Controllers\Traits\MyApprovals;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ApprovalRequest;
use App\Http\Resources\UserResource;
use App\Models\Event;
use App\Models\User;
use App\Models\UserApproval;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Gate;

/**
 * Executive Body Member (EBM) Controller
 *
 * Handles all EBM-related operations including user approvals, registrations,
 * and management of user profiles with enterprise-grade features.
 *
 * Features:
 * - Transaction management with rollback support
 * - Comprehensive logging and error handling
 * - RESTful API design patterns
 * - Pagination and filtering capabilities
 * - Memory-efficient query optimization
 *
 * @package App\Http\Controllers
 * @version 1.0.0
 */
class EBMController extends Controller
{
    use GetPendingApprovals,
        ApproveUsers,
        MyApprovals,
        HandlesUserProfiles,
        GetMyRegistrations;

    /**
     * ================================================================
     * |                    USER APPROVAL MANAGEMENT                  |
     * ================================================================
     */

    /**
     * Approve a user registration
     *
     * @param ApprovalRequest $request Validated approval request
     * @param User $user User instance (resolved by UUID)
     * @return JsonResponse
     *
     * @throws ValidationException
     * @throws Exception
     */
    public function approveUser(Request $request, User $user): JsonResponse
    {
        try {

            $validated = $request->validate([
                'remarks' => 'required|string|min:10|max:255'
            ]);

            $this->logActivity('user_approval_attempt', [
                'ebm_id' => Auth::id(),
                'user_uuid' => $user->uuid,
                'user_id' => $user->id
            ]);

            $this->approveByEBM($user, 'managebyebm', $validated['remarks']);

            $this->logActivity('user_approval_success', [
                'ebm_id' => Auth::id(),
                'user_uuid' => $user->uuid,
                'remarks' => $validated['remarks']
            ]);

            return $this->respondSuccess(
                null,
                'User approved successfully',
                200
            );
        } catch (Exception $e) {
            $this->logError('user_approval_failed', $e, [
                'ebm_id' => Auth::id(),
                'user_uuid' => $user->uuid ?? null
            ]);
            return $this->respondError('Failed to approve user', 500, $e->getMessage());
        }
    }

    /**
     * Reject a user registration
     *
     * @param ApprovalRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function rejectUser(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'remarks' => 'required|string|min:10|max:255'
            ]);

            $this->logActivity('user_reject_attempt', [
                'ebm_id' => Auth::id(),
                'user_uuid' => $user->uuid,
                'user_id' => $user->id
            ]);

            $this->rejectByEBM($user, 'managebyebm', $validated['remarks']);

            $this->logActivity('user_reject_success', [
                'ebm_id' => Auth::id(),
                'user_uuid' => $user->uuid,
                'user_id' => $user->id
            ]);

            return $this->respondSuccess(null, 'User rejected successfully');
        } catch (Exception $e) {
            $this->logError('user_rejection_failed', $e, [
                'ebm_id' => Auth::id(),
                'user_id' => $user->id,
                'user_uuid' => $user->uuid,
            ]);
            return $this->respondError('User rejection failed', 500, $e->getMessage());
        }
    }

    /**
     * Get pending approvals for the authenticated EBM
     *
     * @return AnonymousResourceCollection
     */
    public function getPendingApprovals(Request $request)
    {
        try {
            return $this->respondSuccess(
                $this->getPendingApprovalsForEBM($request),
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
     * Get approved users by the authenticated EBM
     *
     * @return AnonymousResourceCollection
     */
    public function getMyApprovals()
    {
        try {
            return $this->respondSuccess(
                $this->EBMApprovals(),
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
     *  _______________________________________________________________________________
     * |                                                                               |
     * |                          USER REGISTRATION MANAGEMENT                         |
     * |_______________________________________________________________________________|
     *
     */

    /**
     * Create a new user
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function storeUser(RegisterRequest $request)
    {
        Gate::authorize('EBMOnly', User::class);

        try {
            $validated = $request->validated();
            $validated['created_by'] = Auth::id();
            $role = UserRoles::from($validated['role']);

            $response = null;

            DB::transaction(function () use ($validated, $role, &$response) {
                $user = $this->createUserWithProfile($role, $validated);

                if ($user) {
                    $this->logActivity('user_creation_success', [
                        'ebm_id' => Auth::id(),
                        'user_uuid' => $user->uuid,
                        'role' => $role->value
                    ]);

                    $response = $this->respondSuccess(
                        $user->uuid,
                        'User created successfully',
                        201
                    );
                }
            });

            if ($response) {
                return $response;
            }

            return $this->respondError('Failed to create user', 500);
        } catch (Exception $e) {

            $this->logError('user_creation_failed', $e, [
                'ebm_id' => Auth::id(),
                'validated_data' => $validated ?? []
            ]);

            return $this->respondError(
                'Failed to create user',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Get users created by the authenticated EBM
     *
     */
    public function getMyRegistrations()
    {
        try {
            $users = $this->getEBMRegisteredUsers();
            if ($users) {
                return $this->respondSuccess(
                    $users,
                    'Registrations retrieved successfully',
                    200
                );
            }
            return $this->respondSuccess(null, 'No registrations found.');
        } catch (Exception $e) {
            $this->logError('my_registrations_retrieval_failed', $e, [
                'ebm_id' => Auth::id()
            ]);

            return $this->respondError(
                'Failed to create user',
                500,
                $e->getMessage()
            );
        }
    }


    /**
     * ================================================================
     * |                    STATISTICS & REPORTS                     |
     * ================================================================
     */

    /**
     * Get EBM dashboard statistics
     *
     * @return JsonResponse
     */
    public function getDashboardStatistics(): JsonResponse
    {
        try {
            Gate::authorize('EBMOnly', User::class);

            $ebmId = Auth::id();

            $statistics = [
                'created_events' => Event::where('user_id', $ebmId)->count(),
                'pending_approvals' => UserApproval::where('assigned_ebm_id', $ebmId)
                    ->whereNull('ebm_approved_at')
                    ->count(),
                'total_approved' => UserApproval::where('assigned_ebm_id', $ebmId)
                    ->whereNotNull('ebm_approved_at')
                    ->count(),
                'my_registrations' => User::where('created_by', $ebmId)->count(),
                'approved_today' => UserApproval::where('assigned_ebm_id', $ebmId)
                    ->whereDate('ebm_approved_at', Carbon::today())
                    ->count(),
                'pending_by_role' => $this->getPendingByRole(),
                'approval_trend' => $this->getApprovalTrend()
            ];

            return $this->respondSuccess($statistics, 'Statistics retrived successfully');
        } catch (Exception $e) {

            $this->logError('dashboard_statistics_failed', $e, [
                'ebm_id' => Auth::id()
            ]);

            return $this->respondError('Failed to retrieve statistics', 500, $e->getMessage());
        }
    }

    /**
     * Get pending approvals grouped by role
     *
     * @return array
     */
    private function getPendingByRole(): array
    {
        return User::select('role', DB::raw('count(*) as count'))
            ->where('is_approved', false)
            ->whereHas('userApproval', function ($query) {
                $query->where('assigned_ebm_id', Auth::id())
                    ->whereNull('ebm_approved_at');
            })
            ->groupBy('role')
            ->pluck('count', 'role')
            ->toArray();
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
            $count = UserApproval::where('assigned_ebm_id', Auth::id())
                ->whereDate('ebm_approved_at', $date)
                ->count();

            $trend[] = [
                'date' => $date->format('Y-m-d'),
                'count' => $count
            ];
        }

        return $trend;
    }

    /**
     * ================================================================
     * |                    UTILITY METHODS                          |
     * ================================================================
     */

    /**
     * Bulk approve users
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkApprove(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'user_uuids' => 'required|array|min:1|max:50',
                'user_uuids.*' => 'required|string|exists:users,uuid',
                'remarks' => 'required|string|min:10|max:255'
            ]);

            $successCount = 0;
            $failedUsers = [];

            DB::transaction(function () use ($validatedData, &$successCount, &$failedUsers) {
                $userUuids = $validatedData['user_uuids'];
                $remarks = $validatedData['remarks'];
                foreach ($userUuids as $uuid) {
                    try {
                        $user = User::where('uuid', $uuid)->first();
                        if ($user && $this->approveByEBM($user, 'managebyebm', $remarks)) {
                            $successCount++;
                        } else {
                            $failedUsers[] = $uuid;
                        }
                    } catch (Exception $e) {
                        $failedUsers[] = $uuid;
                        $this->logError('bulk_approve_single_failed', $e, [
                            'user_uuid' => $uuid,
                            'ebm_id' => Auth::id()
                        ]);
                    }
                }
            });

            $this->logActivity('bulk_approve_completed', [
                'ebm_id' => Auth::id(),
                'success_count' => $successCount,
                'failed_count' => count($failedUsers),
                'total_requested' => count($validatedData['user_uuids'])
            ]);

            return $this->respondSuccess(
                [
                    'approved_count' => $successCount,
                    'failed_count' => count($failedUsers),
                    'failed_uuids' => $failedUsers
                ],
                $successCount > 0 ? "Successfully approved {$successCount} users" : 'No users were approved',
                $successCount > 0 ? 200 : 500
            );
        } catch (Exception $e) {
            $this->logError('bulk_approve_failed', $e, [
                'ebm_id' => Auth::id(),
                'requested_uuids' => $request->input('user_uuids', [])
            ]);
            return $this->respondError(
                'Bulk approval failed',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * ========================================
     * Get user details by UUID
     * ========================================
     *
     * @param User $user
     * @return JsonResponse
     */
    public function getUserDetails(User $user): JsonResponse
    {
        $userApplication = User::with([
            'musicProfile',
            'managementProfile',
            'userApproval',
            'createdBy:id,uuid,username',
        ])->where('id', $user->id)->first();
        return $this->respondSuccess($userApplication, 'User details retrieved successfully');
    }
}
