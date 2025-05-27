<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminService;
use App\Http\Controllers\Traits\ChecksAdmin;

class AdminController extends Controller
{
    use ChecksAdmin;

    public function __construct(private AdminService $adminService) {}

    public function approve(User $user)
    {

        if ($this->isAdmin()) {
            try {
                $this->adminService->approveUser($user);

                return response()->json(['message' => 'User approved successfully.']);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 404,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    public function clearlock(User $user)
    {
        if ($this->isAdmin()) {
            try {
                $this->adminService->clearAccountLock($user);

                return response()->json(['message' => 'User unlocked successfully.']);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 404,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['message' => 'Unauthorized.'], 403);
    }
}
