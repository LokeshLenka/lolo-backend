<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditRequest;
use App\Http\Requests\UpdateCreditRequest;
use App\Http\Resources\CreditResource;
use App\Models\Credit;
use App\Models\Event;
use App\Services\CreditManagerService;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

/**
 * 🎯 Credit Management Controller
 *
 * Handles all credit-related operations for events including:
 * - Individual credit assignment and management
 * - Bulk credit operations for multiple users
 * - User credit retrieval and viewing
 *
 * @package App\Http\Controllers
 * @version 2.0
 */
class CreditController extends Controller
{
    private const CACHE_TTL = 3600; // 1 hour
    private const PAGINATION_LIMIT = 20;

    public function __construct(
        private readonly CreditManagerService $creditManagerService
    ) {}

    /**
     * 📋 Get paginated list of all credits
     *
     * Returns a cached, paginated list of credits with user and event details.
     * Perfect for admin dashboards and credit management interfaces.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Credit::class);

        try {
            $cacheKey = 'credits_index_' . request('page', 1);

            $credits = Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return Credit::with(['user:id,username,email', 'event:id,uuid,name,credits_awarded'])
                    ->latest()
                    ->simplePaginate(self::PAGINATION_LIMIT);
            });

            return response()->json([
                'success' => true,
                'message' => 'Credits retrieved successfully',
                'data' => CreditResource::collection($credits),
                'meta' => [
                    'current_page' => $credits->currentPage(),
                    'per_page' => $credits->perPage(),
                    'has_more_pages' => $credits->hasMorePages()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve credits index', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve credits',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * ➕ Assign credits to a single user
     *
     * Assigns credits to a specific user for an event. Validates user registration,
     * event completion status, and prevents duplicate assignments.
     *
     * @param CreditRequest $request Validated credit assignment data
     * @param string $uuid Event UUID
     * @return JsonResponse
     */
    public function store(CreditRequest $request, string $uuid): JsonResponse
    {
        try {
            $event = $this->findEventByUuid($uuid);
            $result = $this->creditManagerService->store($request->validated(), $event);

            return response()->json([
                'success' => true,
                'message' => 'Credit assigned successfully',
                'data' => $result
            ], 201);
        } catch (\Exception $e) {
            Log::warning('Credit assignment failed', [
                'event_uuid' => $uuid,
                'user_id' => $request->input('user_id'),
                'error' => $e->getMessage(),
                'assigned_by' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Credit assignment failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * 📦 Bulk assign credits to multiple users
     *
     * Assigns credits to all registered users for an event. Provides detailed
     * processing results including successful and failed assignments.
     *
     * @param CreditRequest $request Validated credit data
     * @param string $uuid Event UUID
     * @return JsonResponse
     */
    public function storeMultiple(CreditRequest $request, string $uuid): JsonResponse
    {
        try {
            $event = $this->findEventByUuid($uuid);
            $result = $this->creditManagerService->storeMultiple($request->validated(), $event);

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Bulk credit assignment completed',
                    'data' => $result
                ],
                // $result['stats']['processed_count'] > 0 ? 201 : 409
            );
        } catch (\Exception $e) {
            Log::error('Bulk credit assignment failed', [
                'event_uuid' => $uuid,
                'error' => $e->getMessage(),
                'assigned_by' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk credit assignment failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * 👁️ View specific credit details
     *
     * Retrieves detailed information about a specific credit record.
     *
     * @param string $id Credit ID
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        Gate::authorize('view', Credit::class);

        try {
            $credit = Credit::with(['event:id,uuid,name,credits_awarded', 'user:id,username,email'])
                ->where('uuid', $uuid)->first();

            return response()->json([
                'success' => true,
                'message' => 'Credit retrieved successfully',
                'data' => new CreditResource($credit)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Credit not found',
                'error' => 'The requested credit record could not be found'
            ], 404);
        }
    }

    /**
     * ✏️ Update individual credit amount
     *
     * Updates the credit amount for a specific user-event combination.
     * Validates the new amount against event limits.
     *
     * @param UpdateCreditRequest $request Validated update data
     * @param string $eventUuid Event UUID
     * @param string $creditUuid Credit UUID
     * @return JsonResponse
     */
    public function update(UpdateCreditRequest $request, string $eventUuid, string $creditUuid): JsonResponse
    {
        try {
            $event = $this->findEventByUuid($eventUuid);
            $credit = $this->findCreditByUuid($creditUuid);

            $result = $this->creditManagerService->update($request->validated(), $event, $credit);

            return response()->json([
                'success' => true,
                'message' => 'Credit updated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::warning('Credit update failed', [
                'event_uuid' => $eventUuid,
                'credit_uuid' => $creditUuid,
                'error' => $e->getMessage(),
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Credit update failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * 🔄 Bulk update credits for an event
     *
     * Updates credit amounts for all users registered to an event.
     * Provides comprehensive processing statistics.
     *
     * @param UpdateCreditRequest $request Validated update data
     * @param string $eventUuid Event UUID
     * @return JsonResponse
     */
    public function updateMultiple(UpdateCreditRequest $request, string $eventUuid): JsonResponse
    {
        try {
            $event = $this->findEventByUuid($eventUuid);
            $result = $this->creditManagerService->updateMultiple($request->validated(), $event);

            return response()->json([
                'success' => true,
                'message' => 'Bulk credit update completed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk credit update failed', [
                'event_uuid' => $eventUuid,
                'error' => $e->getMessage(),
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk credit update failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * 🗑️ Delete a credit record
     *
     * Permanently removes a credit record from the system.
     *
     * @param string $id Credit ID
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $credit = $this->findCreditByUuid($uuid);
            $result = $this->creditManagerService->delete($credit);

            return response()->json([
                'success' => true,
                'message' => 'Credit deleted successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {

            Log::warning('Credit delete failed', [
                'credit_uuid' => $uuid,
                'error' => $e->getMessage(),
                'deleted_by' => Auth::id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Credit not found or could not be deleted',
                'error' => 'The requested credit record could not be found'
            ], 404);
        }
    }


    /**
     * 🗑️ Delete multiple credits
     *
     * Permanently removes a credit record from the system.
     *
     * @param string $id Credit ID
     * @return JsonResponse
     */
    public function destroyMultiple(CreditRequest $request, string $eventUuid): JsonResponse
    {
        try {
            $event = $this->findEventByUuid($eventUuid);

            $validatedData  = $request->validated();

            Arr::forget($validatedData, ['amount']);

            $result = $this->creditManagerService->deleteMultiple($validatedData, $event);

            return response()->json([
                'success' => true,
                'message' => 'Bulk credit delete completed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk credit delete failed', [
                'event_uuid' => $eventUuid,
                'error' => $e->getMessage(),
                'deleted_by' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk credit delete failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * 👤 Get current user's credits
     *
     * Retrieves all credits earned by the authenticated user.
     * Perfect for user dashboards and credit history views.
     *
     * @return JsonResponse
     */
    public function getUserCredits(): JsonResponse
    {
        Gate::authorize('getUserCredits', Credit::class);

        try {
            $userId = Auth::id();
            $cacheKey = "user_credits_{$userId}";

            $credits = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
                return Credit::with(['event:id,uuid,name,credits_awarded,end_date'])
                    ->where('user_id', $userId)
                    ->latest()
                    ->get();
            });

            $totalCredits = $credits->sum('amount');

            return response()->json([
                'success' => true,
                'message' => 'User credits retrieved successfully',
                'data' => [
                    'credits' => CreditResource::collection($credits),
                    'total_credits' => $totalCredits,
                    'credits_count' => $credits->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user credits', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user credits',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * 🔍 Get specific credit details for current user
     *
     * Retrieves detailed information about a specific credit record
     * belonging to the authenticated user.
     *
     * @param string $id Credit ID
     * @return JsonResponse
     */
    public function showUserCreditsDetails(string $id): JsonResponse
    {
        try {
            $credit = Credit::with(['event:id,uuid,name,credits_awarded,end_date'])
                ->where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            Gate::authorize('showUserCreditsDetails', $credit);

            return response()->json([
                'success' => true,
                'message' => 'Credit details retrieved successfully',
                'data' => new CreditResource($credit)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Credit record not found',
                'error' => 'The requested credit record could not be found or you do not have access to it'
            ], 404);
        }
    }

    /**
     * 🔧 Private helper methods
     */

    /**
     * Find event by UUID or throw exception
     */
    private function findEventByUuid(string $uuid): Event
    {
        $event = Event::where('uuid', $uuid)->first();

        if (!$event) {
            throw new \Exception("Event not found with UUID: {$uuid}");
        }

        return $event;
    }

    /**
     * Find credit by UUID or throw exception
     */
    private function findCreditByUuid(string $uuid): Credit
    {
        $credit = Credit::where('uuid', $uuid)->first();

        if (!$credit) {
            throw new \Exception("Credit not found with UUID: {$uuid}");
        }

        return $credit;
    }

    public function uuu(Credit $credit)
    {
        return response()->json($credit);
    }
}
