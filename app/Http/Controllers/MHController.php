<?php

namespace App\Http\Controllers;

use App\Services\MHService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MHController extends Controller
{
    public function __construct(private MHService $mhService) {}

    public function approve(User $user)
    {
        try {
            // Let service handle approval logic and policy checks
            $this->mhService->approveUser($user);
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
            $this->mhService->rejectUser($user);
            return response()->json(['message' => 'User rejected successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }
}
