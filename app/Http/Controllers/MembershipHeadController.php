<?php

namespace App\Http\Controllers;

use App\Services\MembershipHeadService;
use App\Models\User;
use App\Http\Requests\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MembershipHeadController extends Controller
{
    public function __construct(private MembershipHeadService $membershipService) {}

    public function approve(User $user)
    {
        try {
            // Let service handle approval logic and policy checks
            $this->membershipService->approveUser($user);
            return response()->json([
                'message' => 'User approved successfully.',
                'generated_username' => $user->getUserName(),
                'approved_by' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    public function reject(User $user)
    {
        try {
            // Let service handle approval logic and policy checks
            $this->membershipService->rejectUser($user);
            return response()->json(['message' => 'User rejected successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }




    /**
     * EBM
     */
    public function createEBMWithMusic(RegisterRequest $request)
    {
        $data = $request->validated();
        return $this->membershipService->createEBMWithMusic($data);
    }

    public function createEBMWithManagement(RegisterRequest $request)
    {
        $data = $request->validated();
        return $this->membershipService->createEBMWithManagement($data);
    }

    public function promoteEBM(User $user): JsonResponse
    {
        $this->membershipService->promoteUserAsEBM($user);

        return response()->json([
            'message' => 'User promoted as Executive Body Member successfully.',
        ]);
    }

    public function deleteEBM(User $user)
    {
        return $this->membershipService->deleteEBM($user);
    }

    /**
     * Credit Manager
     */
    public function createCreditManagerWithMusic(RegisterRequest $request)
    {
        $data = $request->validated();
        return $this->membershipService->createCreditManagerWithMusic($data);
    }

    public function createCreditManagerWithManagement(RegisterRequest $request)
    {
        $data = $request->validated();
        return $this->membershipService->createCreditManagerWithManagement($data);
    }

    public function promoteCreditManager(User $user): JsonResponse
    {
        $this->membershipService->promoteUserAsCreditManager($user);

        return response()->json([
            'message' => 'User promoted as Credit Manager successfully.',
        ]);
    }

    public function deleteCreditManager(User $user)
    {
        return $this->membershipService->deleteCreditManager($user);
    }
}
