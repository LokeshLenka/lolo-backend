<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\Credit;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 🏆 Credit Management Service
 *
 * Enterprise-grade service for managing event credits with comprehensive
 * validation, error handling, and bulk operations support.
 *
 * Features:
 * - Single & bulk credit assignment
 * - Comprehensive validation rules
 * - Detailed processing statistics
 * - Atomic transactions for data integrity
 * - Intelligent caching for performance
 *
 * @package App\Services
 * @version 2.0
 */
class CreditManagerService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * ✨ Assign credits to a single user
     *
     * Validates and assigns credits to a user for a specific event.
     * Ensures all business rules are met before assignment.
     *
     * @param array $validatedData Validated request data
     * @param Event $event Target event
     * @return array Credit assignment result
     * @throws Exception When validation fails
     */
    public function store(array $validatedData, Event $event): array
    {
        Gate::authorize('createCredit', Credit::class);

        $userId = $validatedData['user_id'];
        $creditsAwarded = $validatedData['amount'] ?? $event->credits_awarded;

        // Comprehensive validation
        $this->validateCreditAssignment($event, $userId, $creditsAwarded);

        return DB::transaction(function () use ($creditsAwarded, $event, $userId) {
            if ($this->isCreditsAlreadyAssigned($event->id, $userId)) {
                throw new Exception('Credits are already assigned to this user for this event');
            }

            $credit = Credit::create([
                'uuid' => Str::uuid(),
                'user_id' => $userId,
                'event_id' => $event->id,
                'assigned_by' => Auth::id(),
                'amount' => $creditsAwarded,
            ]);

            // Clear related cache
            $this->clearUserCache($userId);

            Log::info('Credit assigned successfully', [
                'credit_id' => $credit->id,
                'user_id' => $userId,
                'event_id' => $event->id,
                'amount' => $creditsAwarded,
                'assigned_by' => Auth::id()
            ]);

            return [
                'credit' => $credit->load(['user:id,username,email', 'event:id,uuid,name']),
                'message' => 'Credit assigned successfully'
            ];
        });
    }

    /**
     * 🚀 Bulk assign credits to multiple users
     *
     * Processes credit assignments for all registered users of an event.
     * Continues processing even if individual assignments fail.
     *
     * @param array $validatedData Validated request data
     * @param Event $event Target event
     * @return array Comprehensive processing results
     */
    public function storeMultiple(array $validatedData, Event $event): array
    {
        Gate::authorize('createCredit', Credit::class);

        $creditsAwarded = $validatedData['amount'] ?? $event->credits_awarded;
        $userIds = $validatedData['user_ids'];

        // Pre-validation
        $this->validateEventForBulkOperation($event, $creditsAwarded);

        $results = $this->initializeProcessingResults();

        return DB::transaction(function () use ($userIds, $event, $creditsAwarded, $results) {
            foreach ($userIds as $userId) {
                try {
                    $this->processSingleCreditAssignment($userId, $event, $creditsAwarded, $results);
                } catch (Exception $e) {
                    $this->handleFailedAssignment($userId, $e, $results);
                }
            }

            $this->logBulkOperationResults('store', $event->id, $results);

            return $this->formatBulkOperationResponse($results, 'Bulk credit assignment completed');
        });
    }

    /**
     * 📝 Update individual credit amount
     *
     * Updates credit amount for a specific credit record with validation.
     *
     * @param array $validatedData Validated update data
     * @param Event $event Associated event
     * @param Credit $credit Credit to update
     * @return array Update result
     */
    public function update(array $validatedData, Event $event, Credit $credit): array
    {
        Gate::authorize('updateCredit', Credit::class);

        $newAmount = $validatedData['amount'];

        $this->validateAssignableCredits($event->credits_awarded, $newAmount);

        if ($credit->amount == $newAmount) {
            return [
                'message' => 'No update performed - amount unchanged',
                'credit' => $credit->load(['user:id,username,email', 'event:id,uuid,name'])
            ];
        }

        return DB::transaction(function () use ($credit, $newAmount) {
            $oldAmount = $credit->amount;

            $credit->update(['amount' => $newAmount]);

            // Clear related cache
            $this->clearUserCache($credit->user_id);

            Log::info('Credit updated successfully', [
                'credit_id' => $credit->id,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'updated_by' => Auth::id()
            ]);

            return [
                'message' => 'Credit updated successfully',
                'credit' => $credit->fresh(['user:id,username,email', 'event:id,uuid,name']),
                'changes' => [
                    'old_amount' => $oldAmount,
                    'new_amount' => $newAmount
                ]
            ];
        });
    }

    /**
     * 🔄 Bulk update credits for an event
     *
     * Updates credit amounts for all assigned credits of an event.
     * Provides detailed processing statistics.
     *
     * @param array $validatedData Validated update data
     * @param Event $event Target event
     * @return array Comprehensive update results
     */
    public function updateMultiple(array $validatedData, Event $event): array
    {
        Gate::authorize('updateCredit', Credit::class);

        $newAmount = $validatedData['amount'];
        $userIds = $validatedData['user_ids'];

        $this->validateAssignableCredits($event->credits_awarded, $newAmount);
        $this->preventDuplicateUpdates($event->id, $newAmount);

        $results = $this->initializeProcessingResults();

        return DB::transaction(function () use ($userIds, $event, $newAmount, $results) {
            foreach ($userIds as $userId) {
                try {
                    $this->processSingleCreditUpdate($userId, $event, $newAmount, $results);
                } catch (Exception $e) {
                    $this->handleFailedUpdate($userId, $e, $results);
                }
            }

            // Cache the last update value to prevent duplicate operations
            $this->cacheLastUpdateValue($event->id, $newAmount);

            $this->logBulkOperationResults('update', $event->id, $results);

            return $this->formatBulkOperationResponse($results, 'Bulk credit update completed');
        });
    }



    /**
     *  🗑️ Delete single credit
     */
    public function delete(Credit $credit)
    {
        Gate::authorize('deleteCredit', Credit::class);

        return DB::transaction(function () use ($credit) {

            $credit->delete();

            Log::info('Credit deleted successfully', [
                'credit_id' => $credit->id,
                'deleted_by' => Auth::id(),
            ]);

            return [
                'message' => 'Credit deleted successfully',
            ];
        });
    }

    /**
     *  🗑️ Delete multiple credits
     */

    public function deleteMultiple(array $validatedData, Event $event)
    {
        Gate::authorize('deleteCredit', Credit::class);

        $this->ensureBulkDeleteNotRecentlyPerformed($event->id);

        $userIds = $validatedData['user_ids'];
        $results = $this->initializeProcessingResults();

        return DB::transaction(function () use ($userIds, $event, $results) {
            foreach ($userIds as $userId) {
                try {
                    $this->processSingleCreditDelete($userId, $event, $results);
                } catch (Exception $e) {
                    $this->handleFailedDelete($userId, $e, $results);
                }
            }

            $this->markBulkDeleteAsPerformed($event->id);

            $this->logBulkOperationResults('delete', $event->id, $results);

            return $this->formatBulkOperationResponse($results, 'Bulk credit delete completed');
        });
    }

    /**
     * 🔧 Private Helper Methods
     */

    /**
     * Validate credit assignment prerequisites
     */
    private function validateCreditAssignment(Event $event, int $userId, float $creditsAwarded): void
    {
        $this->validateEventCompletion($event->end_date);
        $this->validateAssignableCredits($event->credits_awarded, $creditsAwarded);
        $this->validateUserRegistration($userId, $event->id);
    }

    /**
     * Validate event for bulk operations
     */
    private function validateEventForBulkOperation(Event $event, float $creditsAwarded): void
    {
        $this->validateEventCompletion($event->end_date);
        $this->validateAssignableCredits($event->credits_awarded, $creditsAwarded);
    }

    /**
     * Check if user is registered for the event
     */
    private function validateUserRegistration(int $userId, int $eventId): void
    {
        if (!$this->isUserRegistered($userId, $eventId)) {
            throw new Exception('User is not registered for this event');
        }
    }

    /**
     * Validate event completion status
     */
    private function validateEventCompletion(Carbon $eventEndDate): void
    {
        if (Carbon::now()->lessThanOrEqualTo($eventEndDate)) {
            throw new Exception('Event must be completed before credits can be assigned');
        }
    }

    /**
     * Validate assignable credit amount
     */
    private function validateAssignableCredits(float $maxCredits, float $creditsAwarded): void
    {
        if ($creditsAwarded > $maxCredits) {
            throw new Exception("Maximum assignable credits for this event is {$maxCredits}");
        }

        if ($creditsAwarded < 0) {
            throw new Exception("Credit amount cannot be negative");
        }
    }

    /**
     * Check if user is registered for event
     */
    private function isUserRegistered(int $userId, int $eventId): bool
    {
        return EventRegistration::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->where('registration_status', RegistrationStatus::CONFIRMED->value)
            ->exists();
    }

    /**
     * Check if credits are already assigned
     */
    private function isCreditsAlreadyAssigned(int $eventId, int $userId): bool
    {
        return Credit::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->exists();
    }

    /**
     * Get all registered user IDs for an event
     */
    private function getRegisteredUserIds(int $eventId): array
    {
        return EventRegistration::where('event_id', $eventId)
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * Initialize processing results structure
     */
    private function initializeProcessingResults(): array
    {
        return [
            'processed' => [],
            'failed' => [],
            'stats' => [
                'total_users' => 0,
                'processed_count' => 0,
                'failed_count' => 0
            ]
        ];
    }

    /**
     * Process single credit assignment
     */
    private function processSingleCreditAssignment(int $userId, Event $event, float $creditsAwarded, array &$results): void
    {
        // Validate individual user
        if (!$this->isUserRegistered($userId, $event->id)) {
            throw new Exception("User not registered for event or event registration not confirmed.");
        }

        if ($this->isCreditsAlreadyAssigned($event->id, $userId)) {
            throw new Exception('Credits already assigned to user');
        }

        // Create credit record
        $credit = Credit::create([
            'uuid' => Str::uuid(),
            'user_id' => $userId,
            'event_id' => $event->id,
            'assigned_by' => Auth::id(),
            'amount' => $creditsAwarded,
        ]);

        $results['processed'][] = [
            'user_id' => $userId,
            'credit_id' => $credit->id,
            'amount' => $creditsAwarded,
            'status' => 'success'
        ];

        $results['stats']['processed_count']++;
        $this->clearUserCache($userId);
    }

    /**
     * Process single credit update
     */
    private function processSingleCreditUpdate(int $userId, Event $event, float $newAmount, array &$results): void
    {
        if (!$this->isUserRegistered($userId, $event->id)) {
            throw new Exception('User not registered for event');
        }

        $credit = Credit::where('user_id', $userId)
            ->where('event_id', $event->id)
            ->first();

        if (!$credit) {
            throw new Exception('No credit record found for user');
        }

        $oldAmount = $credit->amount;
        $credit->update(['amount' => $newAmount]);

        $results['processed'][] = [
            'user_id' => $userId,
            'credit_id' => $credit->id,
            'old_amount' => $oldAmount,
            'new_amount' => $newAmount,
            'status' => 'updated'
        ];

        $results['stats']['processed_count']++;
        $this->clearUserCache($userId);
    }

    /**
     * Handle single credit deletion
     */
    private function processSingleCreditDelete($userId, $event, $results)
    {
        if (!$this->isUserRegistered($userId, $event->id)) {
            throw new Exception('User not registered for event');
        }

        $credit = Credit::where('user_id', $userId)
            ->where('event_id', $event->id)
            ->first();

        if (!$credit) {
            throw new Exception('No credit record found for user');
        }

        $credit->delete();

        $results['processed'][] = [
            'user_id' => $userId,
            'credit_uuid' => $credit->uuid,
            'status' => 'deleted'
        ];

        $results['stats']['processed_count']++;
    }

    /**
     * Handle failed credit assignment
     */
    private function handleFailedAssignment(int $userId, Exception $e, array &$results): void
    {
        $results['failed'][] = [
            'user_id' => $userId,
            'reason' => $e->getMessage(),
            'status' => 'failed'
        ];

        $results['stats']['failed_count']++;
    }

    /**
     * Handle failed credit update
     */
    private function handleFailedUpdate(int $userId, Exception $e, array &$results): void
    {
        $results['failed'][] = [
            'user_id' => $userId,
            'reason' => $e->getMessage(),
            'status' => 'failed'
        ];

        $results['stats']['failed_count']++;
    }

    /**
     * Handle failed credit deletion
     */
    private function handleFailedDelete($userId, $e, $results)
    {
        $results['failed'][] = [
            'user_id' => $userId,
            'reason' => $e->getMessage(),
            'status' => 'failed'
        ];

        $results['stats']['failed_count']++;
    }

    /**
     * Format bulk operation response
     */
    private function formatBulkOperationResponse(array $results, string $message): array
    {
        $results['stats']['total_users'] = $results['stats']['processed_count'] + $results['stats']['failed_count'];

        return [
            'message' => $message,
            'summary' => $results['stats'],
            'processed_users' => $results['processed'],
            'failed_users' => $results['failed'],
            'success_rate' => $results['stats']['total_users'] > 0
                ? round(($results['stats']['processed_count'] / $results['stats']['total_users']) * 100, 2)
                : 0
        ];
    }

    /**
     * Prevent duplicate bulk updates
     */
    private function preventDuplicateUpdates(int $eventId, float $newAmount): void
    {
        $cacheKey = "last_update_amount_{$eventId}";
        $lastAmount = Cache::get($cacheKey);

        if ($lastAmount !== null && $lastAmount == $newAmount) {
            throw new Exception('No update performed - same amount as last bulk update');
        }
    }

    /**
     * Prevent duplicate bulk deletes
     */
    private function ensureBulkDeleteNotRecentlyPerformed(int $eventId): void
    {
        $cacheKey = "credits:bulk_delete:last_event_{$eventId}";
        $wasRecentlyDeleted = Cache::get($cacheKey);

        if ($wasRecentlyDeleted) {
            throw new \Exception('Bulk delete skipped: already performed recently for this event.');
        }
    }

    /**
     * Cache last update value
     */
    private function cacheLastUpdateValue(int $eventId, float $amount): void
    {
        Cache::put("last_update_amount_{$eventId}", $amount, self::CACHE_TTL);
    }

    /**
     * Cahe last deleted value
     */
    private function markBulkDeleteAsPerformed(int $eventId): void
    {
        $cacheKey = "credits:bulk_delete:last_event_{$eventId}";
        Cache::put($cacheKey, true, 120); // Cache for 2 minutes
    }

    /**
     * Clear user-related cache
     */
    private function clearUserCache(int $userId): void
    {
        Cache::forget("user_credits_{$userId}");
    }

    /**
     * Log bulk operation results
     */
    private function logBulkOperationResults(string $operation, int $eventId, array $results): void
    {
        Log::info("Bulk credit {$operation} completed", [
            'event_id' => $eventId,
            'total_users' => $results['stats']['total_users'],
            'processed' => $results['stats']['processed_count'],
            'failed' => $results['stats']['failed_count'],
            'performed_by' => Auth::id()
        ]);
    }
}
