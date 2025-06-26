<?php

namespace App\Http\Controllers;

use App\Enums\PromotedRole;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AdminService;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function getPendingApprovalsForAdmin(?int $limit = 20) {

        
    }


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

}
