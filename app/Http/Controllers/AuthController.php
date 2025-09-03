<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    public function __construct(private AuthService $authService) {}

    /**
     * @request \App\Http\Controllers\Docs\Requests\DocRegisterRequest
     */

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->register($request->validated());

            return $this->respondSuccess(
                [
                    'user' => $user->getUserEmail(),
                    'status' => $user->isApproved() ? 'approved' : 'pending_approval'
                ],
                'Registration successful. Your account is pending approval.',
                201
            );
        } catch (\Exception $e) {
            return $this->respondError(
                'Registration failed.',
                $e->getCode() ?: 500,
                $e->getMessage(),
            );
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $response = $this->authService->attemptLogin(
                $request->validated(),
                $request->ip(),
                $request->userAgent() ?? 'Unknown'
            );

            return $this->respondSuccess(
                $response,
                'Login Sucesssfully',
                200
            );
        } catch (ValidationException $e) {
            return $this->respondError(
                'Login failed.',
                422,
                $e->errors()
            );
        } catch (\Exception $e) {
            return $this->respondError(
                'Login failed.',
                $e->getCode() ?: 500,
                $e->getMessage()
            );
        }
    }

    public function adminlogin(LoginRequest $request): JsonResponse
    {
        try {
            $response = $this->authService->attemptLogin(
                $request->validated(),
                $request->ip(),
                $request->userAgent() ?? 'Unknown'
            );

            // if ($response['user']->role !== 'admin') {
            //     return response()->json([
            //         'message' => 'Unauthorized. Not an admin user.',
            //     ], 403);
            // }

            return $this->respondSuccess(
                $response,
                'Admin login successful.',
                200
            );
        } catch (ValidationException $e) {
            return $this->respondError(
                'Login failed.',
                422,
                $e->errors()
            );
        } catch (\Exception $e) {
            return $this->respondError(
                'Login failed.',
                $e->getCode() ?: 500,
                $e->getMessage()
            );
        }
    }


    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout(
                $request->user(),
                $request->input('token_id')
            );

            return $this->respondSuccess(
                null,
                'Logout successful.',
                200
            );
        } catch (\Exception $e) {
            return $this->respondError(
                'Logout failed.',
                $e->getCode() ?: 500,
                $e->getMessage()
            );
        }
    }

    public function filterNestedFields(array $array, array $filters)
    {
        foreach ($filters as $path) {
            Arr::forget($array, $path); // supports dot notation!
        }

        return $array;
    }


    public function refresh(Request $request): JsonResponse
    {
        try {
            $response = $this->authService->refreshToken($request->user());

            return $this->respondSuccess(
                $response,
                'Token refreshed successfully.',
                200
            );
        } catch (\Exception $e) {
            return $this->respondError(
                'Token refresh failed.',
                $e->getCode() ?: 500,
                $e->getMessage()
            );
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['managementProfile', 'musicProfile']);

        return $this->respondSuccess(
            [
                'user' => $user,
                'abilities' => $request->user()->currentAccessToken()->abilities ?? [],
            ],
            'User profile fetched successfully.',
            200
        );
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

        return $this->respondSuccess(
            [
                'tokens' => $tokens,
            ],
            'Tokens fetched successfully.',
            200
        );
    }

    public function revokeToken(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (!$deleted) {
            return $this->respondError(
                'Token not found.',
                404
            );
        }

        return $this->respondSuccess(
            null,
            'Token revoked successfully.',
            200
        );
    }
}
