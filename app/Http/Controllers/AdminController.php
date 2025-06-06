<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminService;

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

    public function clearlock(User $user)
    {
        try {
            $this->adminService->clearAccountLock($user);
            return response()->json(['message' => 'User unlocked successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }
}
