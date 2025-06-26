<?php

namespace App\Services;

use App\Models\Credit;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Auth;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreditManagerService
{

    /**
     * Stores or assigns credits to a user for a specifired event
     * @param array $validatedData
     * @return JsonResponse
     */

    public function store(array $validatedData, Event $event)
    {
        Gate::authorize('createCredit', Credit::class);

        $eventId = $event->id;
        $userId = $validatedData['user_id'];

        $creditsAwarded = (isset($validatedData['amount'])) ? $validatedData['amount'] : $event->credits_awarded;

        $this->validAssignableCredits($event->credits_awarded, $creditsAwarded);
        $this->EventEndedStatus($event->end_date);

        if (!$this->validRegisteration($userId, $event->id)) {
            throw new \Exception('User is not registered for the event.');
        }

        return DB::transaction(function () use ($creditsAwarded, $eventId, $userId) {

            if ($this->isCreditsAlreadyAssigned($eventId, $userId)) {
                throw new \Exception("Credits are already assigned to the user.");
            }

            $credit = Credit::create([
                'uuid' => Str::uuid(),
                'user_id' => $userId,
                'event_id' => $eventId,
                'assigned_by' => Auth::id(),
                'amount' => $creditsAwarded,
            ]);

            return response()->json([
                'credit' => $credit,
            ], 201); // Created
        });
    }


    /**
     * Mass assings credits to all registered users for a event
     */
    public function storeMultiple(array $validatedData, Event $event)
    {
        Gate::authorize('createCredit', Credit::class);

        $eventId = $event->id;
        $userIds = [];
        $userCount = 0;
        $storedUsers = [];
        $skippedUsers = [];

        $creditsAwarded = (isset($validatedData['amount'])) ? $validatedData['amount'] : $event->credits_awarded;

        $this->EventEndedStatus($event->end_date);
        $this->validAssignableCredits($event->credits_awarded, $creditsAwarded);

        // get all registrations
        $eventRegistrations = EventRegistration::where('event_id', $eventId)->get();

        foreach ($eventRegistrations as $eventRegistration) {
            $userCount = array_push($userIds, $eventRegistration->user_id);
        }

        return DB::transaction(function () use ($userIds, $eventId, $creditsAwarded, &$storedUsers, &$skippedUsers) {

            foreach ($userIds as $userId) {

                // Check if user is registered for the event
                $isRegistered = EventRegistration::where('user_id', $userId)
                    ->where('event_id', $eventId)
                    ->exists();

                if (!$isRegistered) {
                    $skippedUsers[] = [
                        'user_id' => $userId,
                        'reason' => 'Not registered for event',
                    ];
                    continue;
                }

                // Check if credit already assigned
                $alreadyAssigned = $this->isCreditsAlreadyAssigned($eventId, $userId);

                if ($alreadyAssigned) {
                    $skippedUsers[] = [
                        'user_id' => $userId,
                        'reason' => 'Credit already assigned',
                    ];
                    continue;
                }

                // Create new credit
                Credit::create([
                    'uuid' => Str::uuid(),
                    'user_id' => $userId,
                    'event_id' => $eventId,
                    'assigned_by' => Auth::id(),
                    'amount' => $creditsAwarded,
                ]);

                $storedUsers[] = $userId;
            }


            return response()->json([
                'message' => 'Processed credit storage for multiple users.',
                'total_users' => count($userIds),
                'stored_users' => $storedUsers,
                'skipped_users' => $skippedUsers,
            ], count($storedUsers) > 0 ? 201 : 409); // 201 if at least one stored, else conflict
        });
    }


    /**
     * update a specified credit
     */
    public function update(array $validatedData, Event $event, Credit $credit)
    {

        Gate::authorize('updateCredit', Credit::class);

        $eventId = $event->id;
        $userId = $credit->user_id;
        $creditsAwarded = $validatedData['amount'];

        $this->validAssignableCredits($event->credits_awarded, $validatedData['amount']);

        if ($credit->amount == $creditsAwarded) {
            return response()->json([
                'message' => 'No update performed. Same amount submitted.',
            ], 200);
        }
        return DB::transaction(function () use ($credit, $creditsAwarded) {

            // Update and save the credit
            $credit->fill([
                'amount' => $creditsAwarded,
            ]);
            $credit->save();

            return response()->json([
                'message' => 'Credits updated successfully.',
            ], 200);
        });
    }


    /**
     * Massively update credits for all users for a specified event
     */
    public function updateMultiple(array $validatedData, Event $event)
    {
        Gate::authorize('updateCredit', Credit::class);

        $eventId = $event->id;
        $maxCredits = $event->credits_awarded;
        $creditsAwarded = $validatedData['amount'];
        $userIds = [];
        $userCount = 0;
        $updatedUsers = [];
        $unupdatedUsers = [];

        $this->validAssignableCredits($maxCredits, $creditsAwarded);

        $userIds = $this->getAllRegistrations($eventId);
        $userCount = count($userIds);

        return DB::transaction(function () use ($eventId, $userIds, $userCount, $creditsAwarded, &$updatedUsers, &$unupdatedUsers) {

            if (Cache::has("update_credits_value{$eventId}")) {
                if ($creditsAwarded == Cache::get("update_credits_value{$eventId}")) {
                    throw new Exception("No update performed. Same amount submitted.");
                }
            }

            foreach ($userIds as $userId) {
                $user = User::find($userId);

                if ($user && $this->validRegisteration($user->id, $eventId)) {
                    $credit = Credit::where('user_id', $user->id)
                        ->where('event_id', $eventId)
                        ->first();

                    if ($credit) {

                        $credit->update([
                            'amount' => $creditsAwarded,
                        ]);

                        $updatedUsers[] = $user->id;
                    } else {
                        $unupdatedUsers[] = $user->id;
                    }
                } else {
                    $unupdatedUsers[] = $userId; // Either user doesn't exist or not valid registration
                }
            }

            // store last updated credits for multiple users for a specified event
            Cache::add("update_credits_value{$eventId}", $creditsAwarded, 60);


            return response()->json([
                'message' => 'Processed users',
                'total_users' => $userCount,
                'updated_users' => $updatedUsers,
                'not_updated_users' => $unupdatedUsers,
            ]);
        });
    }

    /**
     * --------------------------------------------------------------------------------------------
     * |           private helpers                                                                 |
     * --------------------------------------------------------------------------------------------
     */


    // checks whether the user is registered or not
    private function validRegisteration($userId, $eventId): bool
    {
        if (!EventRegistration::where('user_id', $userId)->where('event_id', $eventId)->exists()) {
            return false;
        }
        return true;
    }

    // checks whether the event is ended or not
    private function EventEndedStatus($eventEndDate): void
    {
        if (Carbon::now()->lessThanOrEqualTo($eventEndDate)) {
            throw new \Exception('Event not yet completed to provide credits');
        }
    }

    // checks whether the provided credits are assignable
    private function validAssignableCredits(float $maxCredits, float $creditsAwarded): void
    {
        if ($maxCredits < $creditsAwarded) {
            throw new \Exception("Maximum credits assigned for this event is {$maxCredits}");
        }
    }

    // checks whether the credits are already assigned or not
    private function isCreditsAlreadyAssigned($eventId, $userId)
    {
        $alreadyAssigned = Credit::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->exists();

        if ($alreadyAssigned) {
            return true;
        }
        return false;
    }

    // get the all registrations for a specified event
    private function getAllRegistrations($eventId)
    {
        $eventRegistrations = EventRegistration::where('event_id', $eventId)->get();

        $userIds = [];

        foreach ($eventRegistrations as $eventRegistration) {
            $userCount = array_push($userIds, $eventRegistration->user_id);
        }

        return $userIds;
    }
}
