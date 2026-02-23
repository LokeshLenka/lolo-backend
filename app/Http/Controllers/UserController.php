<?php

namespace App\Http\Controllers;

use App\Enums\PromotedRole;
use App\Enums\UserApprovalStatus;
use App\Http\Controllers\Traits\HandlesUserProfiles;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateRegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Enums\UserRoles;
use App\Models\Credit;
use App\Models\EventRegistration;
use App\Models\LoginAttempt;
use App\Models\UserApproval;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;

/**
 * UserController - User management for both admin and membership-head
 *
 * Provides user management for administrators and membership-head roles.
 * Includes caching, transactions, filtering, and pagination.
 */
class UserController extends Controller
{
    use HandlesUserProfiles;

    /**
     * Cache configuration
     */
    private const CACHE_TTL = 10; // 2 minutes
    private const CACHE_PREFIX = 'users';
    private const PER_PAGE_DEFAULT = 20;
    private const PER_PAGE_MAX = 100;

    /**
     * Display a paginated listing of all users with advanced filtering.
     *
     * Query Parameters:
     * - search: Search in name, email, registration number
     * - role: Filter by user role
     * - status: Filter by approval status
     * - branch: Filter by branch
     * - year: Filter by academic year
     * - gender: Filter by gender
     * - sort_by: Sort field (created_at, email, etc.)
     * - sort_order: asc or desc
     * - per_page: Items per page (max 100)
     * - with_trashed: Include soft deleted users
     */
    public function index(Request $request): JsonResponse
    {
        try {
            Gate::authorize('viewAny', User::class);

            Log::info('Accessing users list', [
                'viewed_by' => Auth::id(),
                'filters' => $request->only(['search', 'role', 'status', 'branch', 'year', 'gender']),
                'ip' => $request->ip(),
            ]);

            $cacheKey = $this->generateCacheKey('index', $request->all());

            // Apply pagination with per_page parameter and max limit
            $perPage = (int) $request->get('per_page', self::PER_PAGE_DEFAULT);
            $perPage = ($perPage > 0 && $perPage <= self::PER_PAGE_MAX) ? $perPage : self::PER_PAGE_DEFAULT;
            $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request, $perPage) {
                return $this->buildUsersQuery($request)->paginate($perPage);
            });
            return $this->respondSuccess(UserResource::collection($result), 'Users details fetched successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching users list', [
                'error' => $e->getMessage(),
                'viewed_by' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->respondError('Failed to fetch users', 500, $e->getMessage());
        }
    }

    /**
     * Store a newly created user.
     */
    public function store(RegisterRequest $request): JsonResponse
    {
        try {
            Gate::authorize('create', User::class);

            Log::info('Admin creating new user', [
                'viewed_by' => Auth::id(),
                'user_data' => $request->safe()->except(['password']),
                'ip' => $request->ip(),
            ]);

            $incomingRole = $request->input('role');

            if (!in_array($incomingRole, ['management', 'music', 'public'], true)) {
                throw new \Exception('Invalid registration type.');
            }

            $user = DB::transaction(function () use ($request) {
                $data = $request->validated();
                $data['created_by'] = Auth::id();

                $role = UserRoles::from($data['role']);

                $user = $this->createUserWithProfile($role, $data);

                Log::info('User created successfully', [
                    'user_uuid' => $user->uuid,
                    'created_by' => Auth::id(),
                ]);

                return $user;
            });

            $this->clearUserCaches();

            return $this->respondSuccess(new UserResource($user), 'User created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Error creating user', [
                'error' => $e->getMessage(),
                'viewed_by' => Auth::id(),
                'request_data' => $request->safe()->except(['password']),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->respondError('Failed to create user', 500, $e->getMessage());
        }
    }

    /**
     * Display the specified user with full profile information.
     */
    public function show(User $user): JsonResponse
    {
        try {
            Gate::authorize('viewAny', $user);

            Log::info('Viewing user details', [
                'viewed_by' => Auth::id(),
                'user_uuid' => $user->uuid,
                'ip' => request()->ip(),
            ]);

            $cacheKey = $this->generateCacheKey('show', ['uuid' => $user->uuid]);

            $userWithRelations = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
                return $user->load([
                    'musicProfile',
                    'managementProfile',
                    'userApproval',
                    'createdBy:uuid,username,promoted_role',
                ]);
            });

            return $this->respondSuccess($userWithRelations, 'User detpails fetched successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching user details', [
                'error' => $e->getMessage(),
                'viewed_by' => Auth::id(),
                'user_uuid' => $user->uuid,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->respondError('Failed to fetch user details', 500, $e->getMessage());
        }
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateRegisterRequest $request, User $user): JsonResponse
    {
        try {
            Gate::authorize('update', $user);

            Log::info('Updating user', [
                'updated_by' => Auth::id(),
                'user_uuid' => $user->uuid,
                'updates' => $request->safe()->except(['password']),
                'ip' => $request->ip(),
            ]);

            $updatedUser = DB::transaction(function () use ($request, $user) {
                $data = $request->validated();

                // Update email if present
                if (array_key_exists('email', $data)) {
                    $user->update(['email' => $data['email']]);
                }

                // Verify role expectations if provided
                $incomingRole = $data['role'] ?? $user->getUserRole();

                if ($user->getUserRole() === UserRoles::ROLE_MUSIC->value && $incomingRole === UserRoles::ROLE_MUSIC->value) {
                    $musicFields = Arr::only($data, [
                        'first_name',
                        'last_name',
                        'reg_num',
                        'branch',
                        'year',
                        'gender',
                        'lateral_status',
                        'hostel_status',
                        'college_hostel_status',
                        'sub_role',
                    ]);

                    if (!empty($musicFields)) {
                        $user->musicProfile()->update($musicFields);
                    }
                } elseif ($user->getUserRole() === UserRoles::ROLE_MANAGEMENT->value && $incomingRole === UserRoles::ROLE_MANAGEMENT->value) {
                    $managementFields = Arr::only($data, [
                        'first_name',
                        'last_name',
                        'reg_num',
                        'branch',
                        'year',
                        'gender',
                        'lateral_status',
                        'hostel_status',
                        'college_hostel_status',
                        'sub_role',
                    ]);

                    if (!empty($managementFields)) {
                        $user->managementProfile()->update($managementFields);
                    }
                } else {
                    throw new Exception('Mismatch of profile type');
                }

                Log::info('User updated successfully', [
                    'user_uuid' => $user->uuid,
                    'updated_by' => Auth::id(),
                    'user' => $user->refresh(),
                    'profile' => $user->role === UserRoles::ROLE_MANAGEMENT->value
                        ? $user->managementProfile?->refresh()
                        : $user->musicProfile?->refresh(),
                ]);

                return $user->refresh([
                    'musicProfile',
                    'managementProfile',
                    'userApproval',
                ]);
            });

            $this->clearUserCaches($user->uuid);

            return $this->respondSuccess(new UserResource($updatedUser), 'User updated successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error updating user', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
                'user_uuid' => $user->uuid,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->respondError('Failed to update user', 500, 'Internal server error');
        }
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            Gate::authorize('canDeleteUser', $user);

            Log::warning('Deleting user', [
                'deleted_by' => Auth::id(),
                'user_uuid' => $user->uuid,
                'user_email' => $user->email,
                'ip' => request()->ip(),
            ]);

            DB::transaction(function () use ($user) {
                $this->deleteUserWithProfiles($user);

                Log::warning('User deleted successfully', [
                    'user_uuid' => $user->uuid,
                    'deleted_by' => Auth::id(),
                ]);
            });

            $this->clearUserCaches($user->uuid);

            return $this->respondSuccess(null, 'User deleted successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error deleting user', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
                'user_uuid' => $user->uuid,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->respondError('Failed to delete user', 500, $e->getMessage());
        }
    }

    /**
     * Get user statistics for dashboard.
     */
    public function statistics(): JsonResponse
    {
        try {
            Gate::authorize('viewAny', User::class);

            $cacheKey = $this->generateCacheKey('statistics');

            $stats = Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return [
                    'total_users' => User::count(),
                    'pending_approvals' => User::whereHas('userApproval', function ($query) {
                        $query->where('status', 'pending');
                    })->count(),
                    'approved_users' => User::where('is_approved', true)->count(),
                    'music_users' => User::where('role', UserRoles::ROLE_MUSIC->value)->count(),
                    'management_users' => User::where('role', UserRoles::ROLE_MANAGEMENT->value)->count(),
                    'recent_registrations_by_last_week' => User::where('created_at', '>=', Carbon::now()->subDays(7))->count(),
                ];
            });

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            Log::error('Error fetching user statistics', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Bulk approve users.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        try {
            Gate::authorize('viewAny', User::class);

            $request->validate([
                'user_uuids' => 'required|array|min:1',
                'user_uuids.*' => 'required|string|exists:users,uuid',
            ]);

            Log::info('Admin bulk approving users', [
                'admin_id' => Auth::id(),
                'user_uuids' => $request->user_uuids,
                'ip' => $request->ip(),
            ]);

            $approvedCount = DB::transaction(function () use ($request) {
                $users = User::whereIn('uuid', $request->user_uuids)->get();

                foreach ($users as $user) {
                    $user->update(['is_approved' => true]);
                    $user->userApproval?->update(['status' => 'approved']);
                }

                return $users->count();
            });

            $this->clearUserCaches();

            return response()->json([
                'message' => "Successfully approved {$approvedCount} users",
                'approved_count' => $approvedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in bulk approval', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to bulk approve users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Build optimized users query with filters and relationships.
     */
    private function buildUsersQuery(Request $request): Builder
    {
        $query = User::query()->with(['musicProfile', 'managementProfile', 'userApproval', 'createdBy:uuid,username']);

        $this->applySearchFilter($query, $request->get('search'));
        $this->applyRoleFilter($query, $request->get('role'));
        $this->applyCreatedByFilter($query, $request->get('created_by'));
        $this->applyManagementLevelFilter($query, $request->get('management_level'));
        $this->applyPromotedRoleFilter($query, $request->get('promoted_role'));
        $this->applyIsApprovedFilter($query, $request->get('status'));
        $this->applySubRoleFilter($query, $request->get('sub_role'));
        $this->applyBranchFilter($query, $request->get('branch'));
        $this->applyYearFilter($query, $request->get('year'));
        $this->applyGenderFilter($query, $request->get('gender'));
        $this->applySorting($query, $request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'));

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $this->hideAdmin($query);

        return $query;
    }

    /**
     * Apply search filter to query.
     */
    private function applySearchFilter(Builder $query, ?string $search): void
    {
        if (empty($search)) {
            return;
        }

        $query->where(function ($q) use ($search) {
            $q->where('email', 'LIKE', "%{$search}%")
                ->orWhereHas('musicProfile', function ($profile) use ($search) {
                    $profile->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('reg_num', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('managementProfile', function ($profile) use ($search) {
                    $profile->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('reg_num', 'LIKE', "%{$search}%");
                });
        });
    }

    /**
     * Apply role filter to query.
     */
    private function applyRoleFilter(Builder $query, ?string $role): void
    {
        if (!empty($role)) {
            $query->where('role', $role);
        }
    }

    /**
     * Apply created by filter to query.
     */
    private function applyCreatedByFilter(Builder $query, ?string $createdBy): void
    {
        if (!empty($createdBy)) {
            $query->where(function ($q) use ($createdBy) {
                $q->whereHas('createdBy', function ($creator) use ($createdBy) {
                    $creator->where('uuid', $createdBy);
                });
            });
        }
    }

    /**
     * Apply management level filter to query.
     */
    private function applyManagementLevelFilter(Builder $query, ?string $managementLevel): void
    {
        if (!empty($managementLevel)) {
            $query->where('management_level', $managementLevel);
        }
    }

    /**
     * Apply promoted role filter to query.
     */
    private function applyPromotedRoleFilter(Builder $query, ?string $promotedRole): void
    {
        if (!empty($promotedRole)) {
            $query->where('promoted_role', $promotedRole);
        }
    }

    /**
     * Apply approval status filter to query.
     */
    private function applyIsApprovedFilter(Builder $query, ?string $status): void
    {
        if (!empty($status)) {
            if ($status === 'approved') {
                $query->where('is_approved', true);
            } elseif ($status === 'pending') {
                $query->where('is_approved', false);
            }
        }
    }

    /**
     * Apply branch filter to query.
     */
    private function applyBranchFilter(Builder $query, ?string $branch): void
    {
        if (!empty($branch)) {
            $query->where(function ($q) use ($branch) {
                $q->whereHas('musicProfile', function ($profile) use ($branch) {
                    $profile->where('branch', $branch);
                })->orWhereHas('managementProfile', function ($profile) use ($branch) {
                    $profile->where('branch', $branch);
                });
            });
        }
    }

    /**
     * Apply year filter to query.
     */
    private function applyYearFilter(Builder $query, ?string $year): void
    {
        if (!empty($year)) {
            $query->where(function ($q) use ($year) {
                $q->whereHas('musicProfile', function ($profile) use ($year) {
                    $profile->where('year', $year);
                })->orWhereHas('managementProfile', function ($profile) use ($year) {
                    $profile->where('year', $year);
                });
            });
        }
    }

    /**
     * Apply gender filter to query.
     */
    private function applyGenderFilter(Builder $query, ?string $gender): void
    {
        if (!empty($gender)) {
            $query->where(function ($q) use ($gender) {
                $q->whereHas('musicProfile', function ($profile) use ($gender) {
                    $profile->where('gender', $gender);
                })->orWhereHas('managementProfile', function ($profile) use ($gender) {
                    $profile->where('gender', $gender);
                });
            });
        }
    }

    /**
     * Apply sub role filter to query.
     */
    private function applySubRoleFilter(Builder $query, ?string $subRole): void
    {
        if (!empty($subRole)) {
            $query->where(function ($q) use ($subRole) {
                $q->whereHas('musicProfile', function ($profile) use ($subRole) {
                    $profile->where('sub_role', $subRole);
                })->orWhereHas('managementProfile', function ($profile) use ($subRole) {
                    $profile->where('sub_role', $subRole);
                });
            });
        }
    }

    /**
     * Apply sorting to query.
     */
    private function applySorting(Builder $query, string $sortBy, string $sortOrder): void
    {
        $allowedSortFields = ['created_at', 'updated_at', 'email', 'role', 'is_approved'];
        $sortBy = in_array($sortBy, $allowedSortFields, true) ? $sortBy : 'created_at';
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc'], true) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Exclude admin users from results.
     */
    private function hideAdmin(Builder $query): void
    {
        $query->where('role', '!=', 'admin');
    }

    /**
     * Generate cache key for various operations.
     */
    private function generateCacheKey(string $operation, array $params = []): string
    {
        $key = self::CACHE_PREFIX . ":{$operation}";

        if (!empty($params)) {
            $key .= ':' . md5(serialize($params));
        }

        return $key;
    }

    /**
     * Clear user-related caches (MySQL cache driver compatible).
     */
    private function clearUserCaches(?string $userUuid = null): void
    {
        $cacheKeys = [
            $this->generateCacheKey('index', []),
            $this->generateCacheKey('index', ['role' => 'music']),
            $this->generateCacheKey('index', ['role' => 'management']),
            $this->generateCacheKey('index', ['status' => 'approved']),
            $this->generateCacheKey('index', ['status' => 'pending']),
            $this->generateCacheKey('statistics'),
            $this->generateCacheKey('by_role', ['role' => 'music', 'limit' => 10]),
            $this->generateCacheKey('by_role', ['role' => 'management', 'limit' => 10]),
            $this->generateCacheKey('pending_approvals', ['limit' => 20]),
        ];

        if ($userUuid) {
            $cacheKeys[] = $this->generateCacheKey('show', ['uuid' => $userUuid]);
        }

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        $this->clearSearchCaches();

        Log::info('User caches cleared', [
            'user_uuid' => $userUuid,
            'cleared_keys_count' => count($cacheKeys),
        ]);
    }

    /**
     * Clear search-related caches.
     */
    private function clearSearchCaches(): void
    {
        $recentSearches = Cache::get('recent_search_terms', []);

        foreach ($recentSearches as $term) {
            Cache::forget($this->generateCacheKey('search', ['term' => $term, 'limit' => 15]));
        }

        Cache::forget('recent_search_terms');
    }

    /**
     * Track search terms for cache invalidation.
     */
    private function trackSearchTerm(string $term): void
    {
        $recentSearches = Cache::get('recent_search_terms', []);

        $recentSearches[] = $term;
        $recentSearches = array_unique(array_slice($recentSearches, -50));

        Cache::put('recent_search_terms', $recentSearches, 86400);
    }

    /**
     * Helper function to search users efficiently.
     */
    public function searchUsers(string $term, int $limit = 15)
    {
        $this->trackSearchTerm($term);

        $cacheKey = $this->generateCacheKey('search', ['term' => $term, 'limit' => $limit]);

        return Cache::remember($cacheKey, 300, function () use ($term, $limit) {
            return User::where('email', 'LIKE', "%{$term}%")
                ->orWhereHas('musicProfile', function ($query) use ($term) {
                    $query->where('first_name', 'LIKE', "%{$term}%")
                        ->orWhere('last_name', 'LIKE', "%{$term}%")
                        ->orWhere('reg_num', 'LIKE', "%{$term}%");
                })
                ->orWhereHas('managementProfile', function ($query) use ($term) {
                    $query->where('first_name', 'LIKE', "%{$term}%")
                        ->orWhere('last_name', 'LIKE', "%{$term}%")
                        ->orWhere('reg_num', 'LIKE', "%{$term}%");
                })
                ->with(['musicProfile', 'managementProfile'])
                ->limit($limit)
                ->get();
        });
    }


    public function dashboard()
    {
        $user = Auth::user();

        return  $this->respondSuccess(
            $this->getUserDashboardData($user),
            'User dashboard data fetched successfully',
            200
        );
    }


    /**
     * Retrieves analytical dashboard data for a specific user.
     *
     * @param int|string $userId
     * @return array
     */
    protected function getUserDashboardData(User $user): array
    {
        // Eager load required relationships only
        $user->load([
            'userApproval' => fn($q) => $q->latest(),
            'musicProfile',
            'managementProfile',
        ]);

        $creditStats = Credit::where('user_id', $user->id)
            ->selectRaw('
            COALESCE(SUM(amount), 0) as total_credits,
            COUNT(id) as total_assignments,
            MAX(created_at) as last_credit_earned_at
        ')
            ->first();

        /* -------------------- Credit Growth Timeline (Last 12 months) -------------------- */
        $creditGrowth = Credit::where('user_id', $user->id)
            ->selectRaw('
            DATE_FORMAT(created_at, "%Y-%m") as month,
            SUM(amount) as credits_earned,
            COUNT(id) as assignment_count
        ')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // Fill missing months with zero values
        $creditGrowthFormatted = $this->fillMissingMonths($creditGrowth, 12);

        /* -------------------- Event Analytics -------------------- */
        $eventStats = EventRegistration::where('user_id', $user->id)
            ->selectRaw('
            COUNT(*) as total_registrations,
            SUM(registration_status = "confirmed") as confirmed_attendance,
            SUM(payment_status = "pending") as pending_payments
        ')
            ->first();

        /* -------------------- Security Analytics -------------------- */
        $securityStats = LoginAttempt::where('username', $user->username)
            ->selectRaw('
            MAX(created_at) as last_attempt_at,
            SUM(successful = 0 AND created_at >= NOW() - INTERVAL 7 DAY) as failed_attempts_last_7_days
        ')
            ->first();

        $lastLogin = DB::table('login_attempts')
            ->where('username', $user->username)
            ->where('successful', true)
            ->latest('created_at')
            ->value('created_at');

        /* -------------------- Profile Resolution -------------------- */
        $profile = $user->musicProfile ?? $user->managementProfile;
        $approval = $user->userApproval;

        return [
            'profile' => [
                'details' => [
                    'name' => $user->username,
                    'email' => $user->email,
                    'joined_at' => $approval?->approved_at ?? $user->created_at,
                    'is_active' => (bool) $user->is_active,
                    'role' => $user->role,
                    'sub_role' => $profile?->sub_role ?? null,
                    // 'verification_status' => $user->email_verified_at ? 'verified' : 'unverified',
                    'management_level' => $user->management_level,
                    'promoted_role' => $user->promoted_role,
                ],
                'approval' => [
                    'status' => $approval?->status ?? 'not_initiated',
                    'approved_at' => $approval?->approved_at,
                ],
            ],

            'analytics' => [
                'credits' => [
                    'balance' => (float) $creditStats->total_credits,
                    'assignments_count' => (int) $creditStats->total_assignments,
                    'last_earned' => $creditStats->last_credit_earned_at,
                    'growth_timeline' => $creditGrowthFormatted, // ✅ REQUIRED
                ],
                'events' => [
                    'total_registered' => (int) $eventStats->total_registrations,
                    'confirmed' => (int) $eventStats->confirmed_attendance,
                    'pending_payment_count' => (int) $eventStats->pending_payments,
                ],
                'security' => [
                    'last_login' => $lastLogin,
                    'recent_failed_attempts' => (int) $securityStats->failed_attempts_last_7_days,
                    'account_risk' => ((int) $securityStats->failed_attempts_last_7_days > 5) ? 'HIGH' : 'LOW',
                ],
            ],
        ];
    }



    public function registrationStatus(Request $request): JsonResponse
    {
        $email = $request->input('email'); // Get from query param

        if (!$email) {
            return $this->respondError('Email is required', 400);
        }

        // Fetch User and Approval
        $user = User::with('userApproval')->where('email', $email)->first();

        if (!$user) {
            return $this->respondError('User not found', 404);
        }

        if (!($approval = $user->userApproval)) {
            return $this->respondError('Approval record missing', 500);
        }

        // 1. Check for Rejection first (Fast Fail)
        if ($approval->status === UserApprovalStatus::REJECTED->value) {
            return $this->respondSuccess(
                [
                    'stage' => 'rejected',
                    'remarks' => $approval->remarks
                ],
                'Application rejected'
            );
        }

        // 2. Check Final Approval (Success State)
        if (($user->is_approved && is_null($user->userApproval->approved_at)) || $approval->status === UserApprovalStatus::ADMIN_APPROVED->value) {
            return $this->respondSuccess(
                ['stage' => 'approved'],
                'Your application is fully approved!'
            );
        }

        // 3. EBM Workflow Check
        if (is_null($approval->ebm_approved_at)) {
            // Differentiate between "Not Assigned" and "Assigned but Pending"
            if (is_null($approval->assigned_ebm_id)) {
                return $this->respondSuccess(
                    ['stage' => 'pending_assignment'],
                    'Application received, awaiting EBM assignment'
                );
            }

            return $this->respondSuccess(
                ['stage' => 'pending_ebm'],
                'Assigned to EBM, awaiting approval'
            );
        }

        // 4. Membership Head Workflow Check
        if (is_null($approval->membership_head_approved_at)) {
            // Differentiate between "Not Assigned" and "Assigned but Pending"
            if (is_null($approval->assigned_membership_head_id)) {
                return $this->respondSuccess(
                    ['stage' => 'ebm_approved_pending_mh_assignment'],
                    'EBM Approved. Awaiting Membership Head assignment'
                );
            }

            return $this->respondSuccess(
                ['stage' => 'pending_membership_head'],
                'EBM Approved. Awaiting Membership Head approval'
            );
        }

        // 5. Final Processing (Rare case: MH approved but User flag not flipped)
        return $this->respondSuccess(
            ['stage' => 'finalizing'],
            'Approvals complete, finalizing account activation'
        );
    }

    private function fillMissingMonths($creditGrowth, int $monthsBack = 12): array
    {
        $result = [];
        $cumulativeTotal = 0;

        // Create array of last N months
        for ($i = $monthsBack - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $monthName = now()->subMonths($i)->format('M');

            // Find matching data
            $found = $creditGrowth->firstWhere('month', $month);

            if ($found) {
                $cumulativeTotal += $found->credits_earned;
            }

            $result[] = [
                'month' => $month,
                'month_label' => $monthName,
                'credits_earned' => $found ? (float) $found->credits_earned : 0,
                'cumulative_total' => $cumulativeTotal,
                'assignment_count' => $found ? (int) $found->assignment_count : 0,
            ];
        }

        return $result;
    }


    public function getExecutiveBodyMembers()
    {
        // Retrieve users with the specific role and status
        $ebmUsers = User::where('promoted_role', PromotedRole::EXECUTIVE_BODY_MEMBER->value)
            ->where('is_active', true)
            ->where('is_approved', true) // Assuming 'approved_at' is a timestamp
            ->where(function ($query) {
                // User must have at least one of these profiles
                $query->whereHas('musicProfile')
                    ->orWhereHas('managementProfile');
            })
            ->with(['musicProfile:id,user_id,first_name,last_name,phone_no', 'managementProfile:id,user_id,first_name,last_name,phone_no'])
            ->get(['id', 'username']); // distinct selection from users table

        // Format the data to return a clean list of names
        $formattedUsers = $ebmUsers->map(function ($user) {
            // Determine which profile exists and get the name
            $profile = $user->musicProfile ?? $user->managementProfile;

            return [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $profile ? "{$profile->first_name} {$profile->last_name}" : $user->username,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedUsers
        ]);
    }
}
