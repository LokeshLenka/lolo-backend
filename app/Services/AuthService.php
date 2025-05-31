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
use Illuminate\Support\Facades\DB;
use DateTime;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Http\JsonResponse;

// use function Symfony\Component\Clock\now;

class AuthService
{
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
            'last_login_at' => now(),
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
        return DB::transaction(function () use ($userData) {
            if ($userData['role'] === 'admin') {
                throw new \Exception("Admin registration is not allowed.");
            }

            $registrationType = Arr::get($userData, 'registrationtype');

            if (!in_array($registrationType, ['management', 'member'])) {
                throw new \Exception("Invalid registration type.");
            }

            $user = User::create([
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'role' => $userData['role'],
                'is_approved' => false,
            ]);

            if ($registrationType === 'management') {
                $user->managementProfile()->create([
                    'user_id' => $user->id,
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'reg_num' => strtoupper($userData['reg_num']),
                    'branch' => strtoupper($userData['branch']),
                    'year' => $userData['year'],
                    'phone_no' => $userData['phone_no'],
                    'gender' => $userData['gender'],
                    'category_of_interest' => $userData['category_of_interest'],
                    'experience' => $userData['experience'],
                    'interest_towards_lolo' => $userData['interest_towards_lolo'],
                    'any_club' => $userData['any_club'],
                ]);
            } else {
                $user->memberProfile()->create([
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
                    'other_fields_of_interest' => $userData['other_fields_of_interest'],
                    'experience' => $userData['experience'],
                    'passion' => $userData['passion'],
                ]);
            }

            if (in_array($user->role, User::getRolesWithoutAdmin())) {
                $user->userApproval()->create([
                    'user_id' => $user->id,
                    'status' => 'pending',
                ]);
            }

            return $user;
        });
    }


    public function createTokenResponse(User $user): array
    {
        $user->tokens()->delete();

        $abilities = User::getUserAbilities($user);

        $token = $user->createToken(
            name: 'auth-token',
            abilities: $abilities,
            expiresAt: now()->addDays(45)
        );

        //  Load the profiles
        $user->load(['managementProfile', 'memberProfile']);

        // Convert entire user object to array
        $userArray = $user->toArray();

        if (!empty($userArray)) {
            $userArray = Arr::except(
                $userArray,
                [
                    'last_login_ip',
                    'email_verified_at',
                    'locked_until',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ]
            );
        }

        // Clean unwanted fields from managementProfile
        if (!empty($userArray['management_profile'])) {
            $userArray['management_profile'] = Arr::except(
                $userArray['management_profile'],
                [
                    'category_of_interest',
                    'experience',
                    'interest_towards_lolo',
                    'any_club',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ]
            );
        }
        if (!empty($userArray['member_profile'])) {
            $userArray['member_profile'] = Arr::except(
                $userArray['member_profile'],
                [
                    'category_of_interest',
                    'instrument_avail',
                    'others_fileds_of_interest',
                    'experience',
                    'passion',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ]
            );
        }

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
}
