<?php

namespace App\Services;

use App\Enums\UserRoles;
use App\Http\Controllers\Traits\CreatesUser;
use App\Http\Controllers\Traits\HandlesUserProfiles;
use App\Models\User;
use App\Models\LoginAttempt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * AuthService
 *
 * Handles authentication operations including:
 * - Login attempts with rate limiting
 * - Token generation with role-based abilities
 * - User registration for management/music profiles
 * - Token refresh and logout
 */
class AuthService
{
    use HandlesUserProfiles;

    /**
     * Attempt to log in a user with provided credentials
     *
     * @param array $credentials ['username' => string, 'password' => string]
     * @param string $ipAddress Client IP address
     * @param string $userAgent Browser/client user agent
     * @return array Token response with user details and abilities
     * @throws ValidationException If credentials invalid or account locked
     */
    public function attemptLogin(array $credentials, string $ipAddress, string $userAgent): array
    {
        $username = $credentials['username'];
        $password = $credentials['password'];

        $this->checkRateLimit($username, $ipAddress);
        $this->logAttempt($username, $ipAddress, $userAgent, false);

        $user = $this->checkCredentials($username, $password, $ipAddress);

        // Account status checks
        if ($user->isAccountLocked()) {
            throw ValidationException::withMessages([
                'username' => ['Account is temporarily locked due to too many failed attempts.'],
            ]);
        }

        if (!$user->isApproved()) {
            throw ValidationException::withMessages([
                'email' => ['Your account is pending approval.'],
            ]);
        }

        if ($user->trashed()) {
            throw ValidationException::withMessages([
                'username' => ['Account is deactivated.'],
            ]);
        }

        // Success: Reset failed attempts and update login metadata
        $user->resetFailedAttempts();
        $user->update([
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $ipAddress,
        ]);

        $this->logAttempt($username, $ipAddress, $userAgent, true);
        RateLimiter::clear($this->getRateLimitKey($username, $ipAddress));

        return $this->createTokenResponse($user);
    }

    /**
     * Validate username and password
     * Increments failed attempts on invalid credentials
     *
     * @param string $username
     * @param string $password
     * @param string $ipAddress
     * @return User
     * @throws ValidationException If credentials are incorrect
     */
    private function checkCredentials($username, $password, $ipAddress)
    {
        $user = User::where('username', $username)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            if ($user) {
                $user->incrementFailedAttempts();
            }

            RateLimiter::hit($this->getRateLimitKey($username, $ipAddress), 300);

            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }
        return $user;
    }

    /**
     * Register a new user with management or music profile
     *
     * @param array $userData User data including 'registration_type' and 'role'
     * @return User Newly created user
     * @throws \Exception If registration type is invalid
     */
    public function register(array $userData): User
    {
        $registrationType = $userData['registration_type'] ?? null;
        $role = $userData['role'] ?? null;

        Log::info('Registration attempt', ['type' => $registrationType, 'role' => $role]);

        if (!in_array($registrationType, ['management', 'music'])) {
            throw new \Exception("Invalid registration type.");
        }

        if ($registrationType !== $role) {
            throw new \Exception("Registration type must match role.");
        }

        $userData['created_by'] = null;

        if ($registrationType === 'management') {
            $user = $this->createUserWithProfile(UserRoles::ROLE_MANAGEMENT, $userData);
        } elseif ($registrationType === 'music') {
            $user = $this->createUserWithProfile(UserRoles::ROLE_MUSIC, $userData);
        }

        return $user;
    }

    /**
     * Create authentication token with role-based abilities and profile data
     *
     * Returns comprehensive user profile including:
     * - Primary role (management/music)
     * - Promoted role (if any)
     * - Token abilities based on role
     *
     * @param User $user
     * @return array Token response with user profile data
     */
    public function createTokenResponse(User $user): array
    {
        // Delete existing tokens for security
        $user->tokens()->delete();

        // Get role-based abilities
        $abilities = User::getUserAbilities($user);

        // Create new token with 45-day expiration
        $token = $user->createToken(
            name: 'auth-token',
            abilities: $abilities,
            expiresAt: Carbon::now()->addDays(45)
        );

        // Build profile data
        $profileData = [
            'primary_role' => $user->role,
            'management_level' => $user->getManagementLevel() ?? null,
            'promoted_role' => $user->promoted_role ?? null,
            'has_promoted_role' => !is_null($user->promoted_role),
        ];

        return [
            'user' => [
                'username' => $user->username,
                'name' => $user->getUserFirstName(),
                'email' => $user->email,
                'uuid' => $user->uuid ?? null,
                'role' => $user->role,
            ],
            'profile' => $profileData,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at->toISOString(),
            'abilities' => $abilities,
        ];
    }

    /**
     * Logout user by deleting tokens
     *
     * @param User $user
     * @param string|null $tokenId Optional: delete specific token only
     * @return void
     */
    public function logout(User $user, ?string $tokenId = null): void
    {
        if ($tokenId) {
            $user->tokens()->where('id', $tokenId)->delete();
        } else {
            $user->tokens()->delete();
        }
    }

    /**
     * Refresh the current access token
     * Deletes old token and creates new one with same abilities
     *
     * @param User $user
     * @return array New token response
     */
    public function refreshToken(User $user): array
    {
        $user->currentAccessToken()->delete();
        return $this->createTokenResponse($user);
    }

    /**
     * Check if user has exceeded login attempt rate limit
     *
     * @param string $username
     * @param string $ipAddress
     * @return void
     * @throws ValidationException If too many attempts
     */
    private function checkRateLimit(string $username, string $ipAddress): void
    {
        $key = $this->getRateLimitKey($username, $ipAddress);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'username' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }
    }

    /**
     * Generate unique rate limit key for username + IP combination
     *
     * @param string $username
     * @param string $ipAddress
     * @return string
     */
    private function getRateLimitKey(string $username, string $ipAddress): string
    {
        return "login.{$username}.{$ipAddress}";
    }

    /**
     * Log login attempt to database for security auditing
     *
     * @param string $username
     * @param string $ipAddress
     * @param string $userAgent
     * @param bool $successful
     * @return void
     */
    private function logAttempt(string $username, string $ipAddress, string $userAgent, bool $successful): void
    {
        LoginAttempt::create([
            'username' => $username,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'successful' => $successful,
            'metadata' => [
                'timestamp' => Carbon::now()->toISOString(),
                'user_agent_parsed' => $this->parseUserAgent($userAgent),
            ],
        ]);
    }

    /**
     * Parse user agent string to extract device information
     *
     * @param string $userAgent
     * @return array Parsed user agent data
     */
    private function parseUserAgent(string $userAgent): array
    {
        return [
            'full' => $userAgent,
            'is_mobile' => str_contains(strtolower($userAgent), 'mobile'),
            'is_bot' => str_contains(strtolower($userAgent), 'bot'),
        ];
    }
}
