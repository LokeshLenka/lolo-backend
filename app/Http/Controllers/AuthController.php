<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;

class AuthController extends Controller
{

    public function __construct(private AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->register($request->validated());

            return response()->json([
                'message' => 'Registration successful. Your account is pending approval.',
                'user' => $user,
                'status' => $user->is_approved ? 'approved' : 'pending_approval',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $response = $this->authService->login(
                $request->validated(),
                $request->ip(),
                $request->userAgent() ?? 'Unknown'
            );

            return response()->json([
                'message' => 'Login successful.',
                'data' => $response,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Login failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout(
                $request->user(),
                $request->input('token_id')
            );

            return response()->json([
                'message' => 'Logout successful.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Logout failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function refresh(Request $request): JsonResponse
    {
        try {
            $response = $this->authService->refreshToken($request->user());

            return response()->json([
                'message' => 'Token refreshed successfully.',
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Token refresh failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['managementProfile', 'memberProfile', 'userApproval']);

        return response()->json([
            'user' => $user,
            'abilities' => $request->user()->currentAccessToken()->abilities ?? [],
        ]);
    }

    public function tokens(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->select([
            'id',
            'name',
            'abilities',
            'last_used_at',
            'expires_at',
            'created_at'
        ])->get();

        return response()->json([
            'tokens' => $tokens,
        ]);
    }

    public function revokeToken(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Token not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Token revoked successfully.',
        ]);
    }
}
