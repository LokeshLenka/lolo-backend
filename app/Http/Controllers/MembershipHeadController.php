<?php

namespace App\Http\Controllers;

use App\Enums\PromotedRole;
use App\Services\MembershipHeadService;
use App\Models\User;
use App\Http\Requests\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Request;

class MembershipHeadController extends Controller
{
    public function __construct(private MembershipHeadService $membershipService) {}

    public function approve(User $user, Request $request)
    {
        try {
            $validated = $request->validate(
                ['remarks' => 'string | required | min:10 | max:255']
            );
            // Let service handle approval logic and policy checks
            $this->membershipService->approveUser($user, $validated['remarks']);
            return response()->json([
                'message' => 'User approved successfully.',
                'generated_username' => $user->getUserName(),
                'approved_by' => Auth::id(),
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
            $this->membershipService->rejectUser($user, $validated['remarks']);
            return response()->json(['message' => 'User rejected successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }


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
            return response()->json(['error' => 'Invalid role'], 400);
        }

        $this->membershipService->promote($user, $map[$role]);

        return response()->json([
            'message' => 'User promoted successfully.'
        ]);
    }

    public function dePromote(User $user): JsonResponse
    {
        $this->membershipService->dePromoteUser($user);

        return response()->json([
            'message' => 'User de-promoted successfully.',
        ]);
    }
}
