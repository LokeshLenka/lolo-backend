<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\HandlesUserProfiles;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Enums\UserRoles;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateRegisterRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * UserController - Enterprise Grade Admin User Management
 *
 * This controller provides comprehensive user management functionality for administrators.
 * It includes advanced features like caching, transaction management, filtering, and pagination.
 *
 * Features:
 * - RESTful API design
 * - Database query optimization with eager loading
 * - Redis caching for improved performance
 * - Rich transaction management with rollback capabilities
 * - Advanced filtering and search functionality
 * - Pagination for large datasets
 * - Comprehensive logging and error handling
 * - Memory-efficient query building
 * - UUID-based routing support
 *
 * @package App\Http\Controllers
 * @author Your Name
 * @version 1.0.0
 */
class UserController extends Controller
{
    use HandlesUserProfiles;

    /**
     * Cache configuration
     */
    private const CACHE_TTL = 120; // 2 miutes
    private const CACHE_PREFIX = 'users';
    private const PER_PAGE_DEFAULT = 15;
    private const PER_PAGE_MAX = 100;

    /**
     * Display a paginated listing of users with advanced filtering
     *
     * @param Request $request
     * @return AnonymousResourceCollection
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
    public function index(Request $request)
    {
        try {
            Gate::authorize('viewAny', User::class);

            Log::info('Admin accessing users list', [
                'admin_id' => Auth::id(),
                'filters' => $request->only(['search', 'role', 'status', 'branch', 'year', 'gender']),
                'ip' => $request->ip()
            ]);

            $cacheKey = $this->generateCacheKey('index', $request->all());

            $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request) {
                return $this->buildUsersQuery($request)->paginate(
                    min($request->get('per_page', self::PER_PAGE_DEFAULT), self::PER_PAGE_MAX)
                );
            });

            return UserResource::collection($result);
        } catch (\Exception $e) {
            Log::error('Error fetching users list', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to fetch users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created user in storage
     *
     * @param StoreUserRequest $request
     * @return JsonResponse
     */
    public function store(RegisterRequest $request): JsonResponse
    {
        try {
            Gate::authorize('create', User::class);

            Log::info('Admin creating new user', [
                'admin_id' => Auth::id(),
                'user_data' => $request->safe()->except(['password']),
                'ip' => $request->ip()
            ]);

            if (!in_array($request['role'], ['management', 'music', 'public'])) {
                throw new \Exception("Invalid registration type.");
            }

            $user = DB::transaction(function () use ($request) {
                $data = $request->validated();
                $data['created_by'] = Auth::id();

                $role = UserRoles::from($data['role']);
                $user = $this->createUserWithProfile($role, $data);

                Log::info('User created successfully', [
                    'user_uuid' => $user->uuid,
                    'created_by' => Auth::id()
                ]);

                return $user;
            });

            // Clear relevant caches
            $this->clearUserCaches();

            return response()->json([
                'message' => 'User created successfully',
                'data' => new UserResource($user)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating user', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
                'request_data' => $request->safe()->except(['password']),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to create user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified user with full profile information
     *
     * @param User $user
     * @return JsonResponse
     */
    public function show(User $user): JsonResponse
    {
        try {
            Gate::authorize('viewAny', User::class);

            Log::info('Admin viewing user details', [
                'admin_id' => Auth::id(),
                'user_uuid' => $user->uuid,
                'ip' => request()->ip()
            ]);

            $cacheKey = $this->generateCacheKey('show', ['uuid' => $user->uuid]);

            $userWithRelations = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
                return $user->load([
                    'musicProfile',
                    'managementProfile',
                    'userApproval',
                    'createdBy:uuid,username,promoted_role'
                ]);
            });

            return response()->json([
                'data' => new UserResource($userWithRelations)
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user details', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
                'user_uuid' => $user->uuid,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to fetch user details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified user in storage
     *
     * @param UpdateUserRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function update(UpdateRegisterRequest $request, User $user): JsonResponse
    {
        try {
            Gate::authorize('viewAny', User::class);

            Log::info('Admin updating user', [
                'admin_id' => Auth::id(),
                'user_uuid' => $user->uuid,
                'updates' => $request->safe()->except(['password']),
                'ip' => $request->ip()
            ]);

            $updatedUser = DB::transaction(function () use ($request, $user) {
                $data = $request->validated();
                // dd($data);

                Log::info($data);

                // Update user basic information
                $user->update([
                    'email' => $data['email'],
                ]);

                // Update profile information based on user role
                if ($user->role === UserRoles::ROLE_MUSIC->value && isset($data['music_profile'])) {
                    $user->musicProfile()->update([
                        'first_name' => $data['music_profile']['first_name'],
                        'last_name' => $data['music_profile']['last_name'],
                        'reg_num' => $data['music_profile']['reg_num'],
                        'branch' => $data['music_profile']['branch'],
                        'year' => $data['music_profile']['year'],
                        'phone_no' => $data['music_profile']['phone_no'],
                        'gender' => $data['music_profile']['gender'],
                        'sub_role' => $data['music_profile']['sub_role'],
                    ]);
                } elseif ($user->role === UserRoles::ROLE_MANAGEMENT->value && isset($data['management_profile'])) {
                    $user->managementProfile()->update([
                        'first_name' => $data['management_profile']['first_name'],
                        'last_name' => $data['management_profile']['last_name'],
                        'reg_num' => $data['management_profile']['reg_num'],
                        'branch' => $data['management_profile']['branch'],
                        'year' => $data['management_profile']['year'],
                        'phone_no' => $data['management_profile']['phone_no'],
                        'gender' => $data['management_profile']['gender'],
                        'sub_role' => $data['management_profile']['sub_role'] ?? null,
                    ]);
                }

                Log::info('User updated successfully', [
                    'user_uuid' => $user->uuid,
                    'updated_by' => Auth::id(),
                    'user' => $user->refresh(),
                    'profile' => $user->role === UserRoles::ROLE_MANAGEMENT->value
                        ? $user->managementProfile->refresh()
                        : $user->musicProfile->refresh()

                ]);

                return $user->fresh([
                    'musicProfile',
                    'managementProfile',
                    'userApproval'
                ]);
            });

            // Clear relevant caches
            $this->clearUserCaches($user->uuid);

            return response()->json([
                'message' => 'User updated successfully',
                'data' => new UserResource($updatedUser)
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating user', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
                'user_uuid' => $user->uuid,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to update user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified user from storage
     *
     * @param User $user
     * @return JsonResponse
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            Gate::authorize('viewAny', User::class);

            Log::warning('Admin deleting user', [
                'admin_id' => Auth::id(),
                'user_uuid' => $user->uuid,
                'user_email' => $user->email,
                'ip' => request()->ip()
            ]);

            DB::transaction(function () use ($user) {
                $this->deleteUserWithProfiles($user);

                Log::warning('User deleted successfully', [
                    'user_uuid' => $user->uuid,
                    'deleted_by' => Auth::id()
                ]);
            });

            // Clear relevant caches
            $this->clearUserCaches($user->uuid);

            return response()->json([
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting user', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
                'user_uuid' => $user->uuid,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to delete user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get users statistics for dashboard
     *
     * @return JsonResponse
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
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to fetch statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Bulk approve users
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        try {
            // Gate::authorize('bulkApprove', User::class);

            $request->validate([
                'user_uuids' => 'required|array|min:1',
                'user_uuids.*' => 'required|string|exists:users,uuid'
            ]);

            Log::info('Admin bulk approving users', [
                'admin_id' => Auth::id(),
                'user_uuids' => $request->user_uuids,
                'ip' => $request->ip()
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
                'approved_count' => $approvedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error in bulk approval', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to bulk approve users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Export users data
     *
     * @param Request $request
     * @return JsonResponse
     */

    // public function export(Request $request): JsonResponse
    // {
    //     try {
    //         // Gate::authorize('export', User::class);

    //         $request->validate([
    //             'format' => 'required|in:csv,excel',
    //             'filters' => 'sometimes|array'
    //         ]);

    //         Log::info('Admin exporting users data', [
    //             'admin_id' => Auth::id(),
    //             'format' => $request->format,
    //             'filters' => $request->filters ?? [],
    //             'ip' => $request->ip()
    //         ]);

    //         // This would typically queue a job for large exports
    //         // For now, we'll return a success message
    //         return response()->json([
    //             'message' => 'Export initiated successfully. You will receive an email when ready.',
    //             'export_id' => uniqid('export_')
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Error initiating export', [
    //             'error' => $e->getMessage(),
    //             'admin_id' => Auth::id(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'message' => 'Failed to initiate export',
    //             'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
    //         ], 500);
    //     }
    // }

    /**
     * Build optimized users query with filters and relationships
     *
     * @param Request $request
     * @return Builder
     */
    private function buildUsersQuery(Request $request): Builder
    {
        $query = User::query()
            ->with(['musicProfile', 'managementProfile', 'userApproval', 'createdBy:uuid,username']);
        // ->selectNot(['deleted_at']);

        // Apply scopes and filters
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

        // Include soft deleted if requested
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        // Hides the admin from any filters
        $this->hideAdmin($query);

        return $query;
    }

    /**
     * Apply search filter to query
     *
     * @param Builder $query
     * @param string|null $search
     * @return void
     */
    private function applySearchFilter(Builder $query, ?string $search): void
    {
        if (empty($search)) return;

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
     * Apply role filter to query
     *
     * @param Builder $query
     * @param string|null $role
     * @return void
     */
    private function applyRoleFilter(Builder $query, ?string $role): void
    {
        if (!empty($role)) {
            $query->where('role', $role);
        }
    }

    /**
     * Apply created by filter to query
     *
     * @param Builder $query
     * @param string|null $role
     * @return void
     */
    private function applyCreatedByFilter(Builder $query, ?string $createdBy): void
    {
        if (!empty($createdBy)) {
            $query->where(function ($q) use ($createdBy) {
                $q->whereHas('createdBy', function ($user) use ($createdBy) {
                    $user->where('uuid', $createdBy);
                });
            });
        }
    }

    /**
     * Apply management level filter to query
     *
     * @param Builder $query
     * @param string|null $role
     * @return void
     */
    private function applyManagementLevelFilter(Builder $query, ?string $managementLevel): void
    {
        if (!empty($managementLevel)) {
            $query->where('management_level', $managementLevel);
        }
    }

    /**
     * Apply promoted role filter to query
     *
     * @param Builder $query
     * @param string|null $role
     * @return void
     */
    private function applyPromotedRoleFilter(Builder $query, ?string $promotedRole): void
    {
        if (!empty($promotedRole)) {
            $query->where('promoted_role', $promotedRole);
        }
    }

    /**
     * Apply approval status filter to query
     *
     * @param Builder $query
     * @param string|null $status
     * @return void
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
     * Apply branch filter to query
     *
     * @param Builder $query
     * @param string|null $branch
     * @return void
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
     * Apply year filter to query
     *
     * @param Builder $query
     * @param string|null $year
     * @return void
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
     * Apply gender filter to query
     *
     * @param Builder $query
     * @param string|null $gender
     * @return void
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
     * Apply sub role filter to query
     *
     * @param Builder $query
     * @param string|null $gender
     * @return void
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
     * Apply sorting to query
     *
     * @param Builder $query
     * @param string $sortBy
     * @param string $sortOrder
     * @return void
     */
    private function applySorting(Builder $query, string $sortBy, string $sortOrder): void
    {
        $allowedSortFields = ['created_at', 'updated_at', 'email', 'role', 'is_approved'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'created_at';
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Hides the admin
     */
    private function hideAdmin(Builder $query)
    {
        $query->whereNot('role', 'admin');
    }

    /**
     * Generate cache key for various operations
     *
     * @param string $operation
     * @param array $params
     * @return string
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
     * Clear user-related caches (MySQL cache driver compatible)
     *
     * @param string|null $userUuid
     * @return void
     */
    private function clearUserCaches(?string $userUuid = null): void
    {
        $cacheKeys = [
            // Clear index/list caches with common filter combinations
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

        // Clear specific user cache if UUID provided
        if ($userUuid) {
            $cacheKeys[] = $this->generateCacheKey('show', ['uuid' => $userUuid]);
        }

        // Clear all identified cache keys
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Clear search caches by pattern (if supported by MySQL driver)
        $this->clearSearchCaches();

        Log::info('User caches cleared', [
            'user_uuid' => $userUuid,
            'cleared_keys_count' => count($cacheKeys)
        ]);
    }

    /**
     * Clear search-related caches
     * Note: MySQL cache driver doesn't support pattern deletion,
     * so we maintain a list of recent search terms
     *
     * @return void
     */
    private function clearSearchCaches(): void
    {
        // Get recent search terms from a tracking cache
        $recentSearches = Cache::get('recent_search_terms', []);

        foreach ($recentSearches as $term) {
            Cache::forget($this->generateCacheKey('search', ['term' => $term, 'limit' => 15]));
        }

        // Clear the tracking cache
        Cache::forget('recent_search_terms');
    }

    /**
     * Track search terms for cache invalidation
     *
     * @param string $term
     * @return void
     */
    private function trackSearchTerm(string $term): void
    {
        $recentSearches = Cache::get('recent_search_terms', []);

        // Add new term and keep only last 50 searches
        $recentSearches[] = $term;
        $recentSearches = array_unique(array_slice($recentSearches, -50));

        Cache::put('recent_search_terms', $recentSearches, 86400); // 24 hours
    }

    /**
     * Helper function to get users by role with caching
     *
     * @param UserRoles $role
     * @param int $limit
     * @return Collection
     */
    // public function getUsersByRole(UserRoles $role, int $limit = 10)
    // {
    //     $cacheKey = $this->generateCacheKey('by_role', ['role' => $role->value, 'limit' => $limit]);

    //     return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($role, $limit) {
    //         return User::where('role', $role->value)
    //             ->with(['musicProfile', 'managementProfile'])
    //             ->limit($limit)
    //             ->get();
    //     });
    // }



    /**
     * Helper function to search users efficiently
     *
     * @param string $term
     * @param int $limit
     * @return Collection
     */
    public function searchUsers(string $term, int $limit = 15)
    {
        // Track search term for cache invalidation
        $this->trackSearchTerm($term);

        $cacheKey = $this->generateCacheKey('search', ['term' => $term, 'limit' => $limit]);

        return Cache::remember($cacheKey, 300, function () use ($term, $limit) { // 5 minutes cache for searches
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
}
