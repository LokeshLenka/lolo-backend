<?php

namespace App\Services;

use App\Models\User;
use App\Models\LoginAttempt;
use App\Models\ManagementProfile;
use App\Models\MemberProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AuthService
{
    private const ROLE_ABILITIES = [
        User::ROLE_ADMIN => ['*'],
        User::ROLE_MCH => ['users:approve', 'users:manage', 'blogs:create', 'blogs:update', 'teams:manage'],
        User::ROLE_EBM => ['events:manage', 'events:create', 'blogs:create', 'blogs:update'],
        User::ROLE_CM => ['credits:manage', 'credits:create', 'credits:update'],
        User::ROLE_EP => ['blogs:create', 'blogs:update'],
        User::ROLE_EO => ['blogs:create', 'blogs:update'],
        User::ROLE_MEMBER => ['events:register'],
    ];

    public function login(
        array $credentials,
        // string $ipAddress,
        // string $userAgent
    ): array {
        $email = $credentials['email'];
        $password = $credentials['password'];

        // $this->checkRateLimit($email, $ipAddress);
        // $this->logAttempt($email, $ipAddress, $userAgent, false);

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            // if ($user) {
            //     $user->incrementFailedAttempts();
            // }

            // RateLimiter::hit($this->getRateLimitKey($email, $ipAddress), 300); // 5minutes

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // if (!$user->isApproved()) {
        //     throw ValidationException::withMessages([
        //         'email' => ['Your account is pending approval.'],
        //     ]);
        // }

        // if ($user->trashed()) {
        //     throw ValidationException::withMessages([
        //         'email' => ['Account is deactivated.'],
        //     ]);
        // }

        // $user->resetFailedAttempts();
        // $user->update([
        //     'last_login_at' => now(),
        //     'last_login_ip' => $ipAddress,
        // ]);

        // $this->logAttempt($email, $ipAddress, $userAgent, true);
        // RateLimiter::clear($this->getRateLimitKey($email, $ipAddress));

        return $this->createTokenResponse($user);

        // return $user;
    }

    public function register(array $userData): User
    {
        $user = User::create([
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'role' => $userData['role'],
            'is_approved' => false,
        ]);

        $userType = $userData['registrationtype'] ?? 'unknown';

        if ($userType === 'management') {
            ManagementProfile::create([
                'user_id' => $user->id,
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'reg_num' => $userData['reg_num'],
                'branch' => $userData['branch'],
                'year' => $userData['year'],
                'phone_no' => $userData['phone_no'],
                'gender' => $userData['gender'],
                'category_of_interest' => $userData['category_of_interest'],
                'experience' => $userData['experience'],
                'interest_towards_lolo' => $userData['interest_towards_lolo'],
                'any_club' => $userData['any_club'],
            ]);
        } elseif ($userType === 'member') {
            MemberProfile::create([
                'user_id' => $user->id,
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'reg_num' => $userData['reg_num'],
                'branch' => $userData['branch'],
                'year' => $userData['year'],
                'phone_no' => $userData['phone_no'],
                'gender' => $userData['gender'],
                'category_of_interest' => $userData['category_of_interest'],
                'instrument_avail' => $userData['instrument_avail'],
                'other_fileds_of_interset' => $userData['other_fileds_of_interset'],
                'experience' => $userData['experience'],
                'passion' => $userData['passion'],
            ]);
        }

        if (in_array($user->role, $this->getRolesWithoutAdmin())) {
            $user->userApproval()->create([
                'user_id' => $user->id,
                'status' => 'pending',
            ]);
        }

        return $user;
    }

    public function createTokenResponse(User $user): array
    {
        $user->tokens()->delete();

        $abilities = $this->getUserAbilities($user);

        $token = $user->createToken(
            name: 'auth-token',
            abilities: $abilities,
            expiresAt: now()->addDays(45)
        );

        return [
            'user' => $user->load(['managementProfile', 'memberProfile']),
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at,
            'abilities' => $abilities,
        ];
    }

    public function logout(User $user, ?string $tokenId = null): void
    {
        if ($tokenId) {
            $user->tokens()->where('id', $tokenId)->delete();
        } else {
            $user->tokens()->delete();
        }
    }

    public function refreshToken(User $user): array
    {
        $user->currentAccessToken()->delete();
        return $this->createTokenResponse($user);
    }

    private function getUserAbilities(User $user): array
    {
        return self::ROLE_ABILITIES[$user->role] ?? ['read'];
    }

    private function checkRateLimit(string $email, string $ipAddress): void
    {
        $key = $this->getRateLimitKey(Str::lower($email), $ipAddress);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }
    }

    private function getRateLimitKey(string $email, string $ipAddress): string
    {
        return "login.{$email}.{$ipAddress}";
    }

    private function logAttempt(string $email, string $ipAddress, string $userAgent, bool $successful): void
    {
        LoginAttempt::create([
            'email' => $email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'successful' => $successful,
            'metadata' => [
                'timestamp' => now()->toIso8601String(),
                'user_agent_parsed' => $this->parseUserAgent($userAgent),
            ],
        ]);
    }

    private function parseUserAgent(string $userAgent): array
    {
        return [
            'full' => $userAgent,
            'is_mobile' => str_contains(strtolower($userAgent), 'mobile'),
            'is_bot' => str_contains(strtolower($userAgent), 'bot'),
        ];
    }

    private function getRolesWithoutAdmin(): array
    {
        return [
            User::ROLE_EBM,
            User::ROLE_EP,
            User::ROLE_EO,
            User::ROLE_CM,
        ];
    }
}
