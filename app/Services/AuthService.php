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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;


class AuthService
{
    use HandlesUserProfiles;
    public function attemptLogin(array $credentials, string $ipAddress, string $userAgent): array
    {

        $username = $credentials['username'];
        $password = $credentials['password'];

        $this->checkRateLimit($username, $ipAddress);
        $this->logAttempt($username, $ipAddress, $userAgent, false);

        $user = $this->checkCredientials($username, $password, $ipAddress);

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

        $user->resetFailedAttempts();
        $user->update([
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $ipAddress,
        ]);

        $this->logAttempt($username, $ipAddress, $userAgent, true);
        RateLimiter::clear($this->getRateLimitKey($username, $ipAddress));

        return $this->createTokenResponse($user);
    }

    private function checkCredientials($username, $password, $ipAddress)
    {
        $user = User::where('username', $username)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            if ($user) {
                $user->incrementFailedAttempts();
            }

            RateLimiter::hit($this->getRateLimitKey($username, $ipAddress), 300);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }
        return $user;
    }

    public function register(array $userData): User
    {
        $registrationType = Arr::get($userData, 'registration_type');

        if (!in_array($registrationType, ['management', 'music'])) {
            throw new \Exception("Invalid registration type.");
        }

        if ($registrationType === 'management') {
            $user = $this->createUserWithProfile(UserRoles::ROLE_MANAGEMENT, null, $userData);
        } elseif ($registrationType === 'music') {
            $user = $this->createUserWithProfile(UserRoles::ROLE_MUSIC, null, $userData);
        }

        return $user;
    }


    public function createTokenResponse(User $user): array
    {
        $user->tokens()->delete();

        $abilities = User::getUserAbilities($user);

        $token = $user->createToken(
            name: 'auth-token',
            abilities: $abilities,
            expiresAt: Carbon::now()->addDays(45)
        );

        $userArray = $user->load(
            [
                'managementProfile:id,user_id,first_name,last_name,reg_num,branch,year,phone_no,gender,sub_role',
                'musicProfile:id,user_id,first_name,last_name,reg_num,branch,year,phone_no,gender,sub_role'
            ]
        );

        return [
            'user' => $userArray,
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

    private function checkRateLimit(string $username, string $ipAddress): void
    {
        $key = $this->getRateLimitKey(Str::lower($username), $ipAddress);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'username' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }
    }

    private function getRateLimitKey(string $username, string $ipAddress): string
    {
        return "login.{$username}.{$ipAddress}";
    }

    private function logAttempt(string $username, string $ipAddress, string $userAgent, bool $successful): void
    {
        LoginAttempt::create([
            'username' => $username,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'successful' => $successful,
            'metadata' => [
                'timestamp' => Carbon::now(),
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
}
