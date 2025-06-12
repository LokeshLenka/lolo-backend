<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AdminService;
use Auth;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function __construct(private AdminService $adminService) {}

    public function approve(User $user)
    {
        try {
            // Let service handle approval logic and policy checks
            $this->adminService->approveUser($user);
            return response()->json([
                'message' => 'User approved successfully.',
                'Generated username' => $user->getUserName(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    public function reject(User $user)
    {
        try {
            // Let service handle approval logic and policy checks
            $this->adminService->rejectUser($user);
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



    /**
     * EBM
     */
    public function createEBMWithMusic(RegisterRequest $request)
    {
        $user = $this->adminService->createEBMWithMusic($request->validated());
        return response()->json($user, 201);
    }

    public function createEBMWithManagement(RegisterRequest $request)
    {
        $user = $this->adminService->createEBMWithManagement($request->validated());
        return response()->json($user, 201);
    }

    public function promoteEBM(User $user)
    {
        $this->adminService->promoteUserAsEBM($user);

        return response()->json([
            'message' => 'User promoted as Executive Body Member successfully.',
        ]);
    }

    public function deleteEBM(User $user)
    {
        $this->adminService->deleteEBM($user);
        return response()->json(['message' => 'EBM deleted.']);
    }

    /**
     * Credit manager
     */
    public function createCreditManagerWithMusic(RegisterRequest $request)
    {
        $user = $this->adminService->createCreditManagerWithMusic($request->validated());
        return response()->json($user, 201);
    }

    public function createCreditManagerWithManagement(RegisterRequest $request)
    {
        $user = $this->adminService->createCreditManagerWithManagement($request->validated());
        return response()->json($user, 201);
    }

    public function promoteCreditManager(User $user): JsonResponse
    {
        $this->adminService->promoteUserAsCreditManager($user);

        return response()->json([
            'message' => 'User promoted as Credit Manager successfully.',
        ]);
    }

    public function deleteCreditManager(User $user)
    {
        $this->adminService->deleteCreditManager($user);
        return response()->json(['message' => 'Credit Manager deleted.']);
    }

    /**
     *  Membership Head
     */
    public function createMemberShipHeadWithMusic(RegisterRequest $request)
    {
        $user = $this->adminService->createMemberShipHeadWithMusic($request->validated());
        return response()->json($user, 201);
    }

    public function createMemberShipHeadWithManagement(RegisterRequest $request)
    {
        $user = $this->adminService->createMemberShipHeadWithManagement($request->validated());
        return response()->json($user, 201);
    }

    public function promoteMembershipHead(User $user): JsonResponse
    {
        $this->adminService->promoteUserAsMemberShipHead($user);

        return response()->json([
            'message' => 'User promoted as Membership Head successfully.',
        ]);
    }

    public function deleteMemberShipHead(User $user)
    {
        $this->adminService->deleteMemberShipHead($user);
        return response()->json(['message' => 'Membership Head deleted.']);
    }
}
