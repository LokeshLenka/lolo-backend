<?php

namespace App\Http\Controllers;

use App\Models\Credit;
use App\Models\Event;
use Illuminate\Http\Request;
use App\Models\User;
use App\Policies\CreditPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Http\Requests\CreditRequest;
use App\Models\EventRegistration;
use Exception;
use Illuminate\Contracts\Support\ValidatedData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\CreditResource;

class CreditController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', Credit::class);

        $credits = Credit::with(['user', 'event'])->get();

        // unset($credits, [$credits['last_login_ip']]);

        // return response()->json([
        //     'credits' => $credits,
        // ], 200);

        return CreditResource::collection($credits);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreditRequest $request, Event $event)
    {
        Gate::authorize('create_credit', $event);

        $validatedData = $request->validated();

        $eventId = $event->id;
        $userId = $validatedData['user_id'];

        if (!EventRegistration::where('user_id', $userId)->where('event_id', $eventId)->exists()) {
            throw new Exception('User is not registered for the event.');
        }

        return DB::transaction(function () use ($validatedData, $eventId) {
            $alreadyAssigned = Credit::where('user_id', $validatedData['user_id'])
                ->where('event_id', $eventId)
                ->exists();

            if ($alreadyAssigned) {
                return response()->json([
                    'message' => 'Credits are already assigned to the user.',
                ], 409); // Conflict
            }

            $credit = Credit::create([
                'user_id' => $validatedData['user_id'],
                'event_id' => $eventId,
                'assigned_by' => Auth::id(),
                'amount' => $validatedData['amount'],
            ]);

            return response()->json([
                'credit' => $credit,
            ], 201); // Created
        });
    }

    public function storeMultiple(CreditRequest $request, Event $event)
    {
        Gate::authorize('create_credit', $event);

        $validatedData = $request->validated();
        $userIds = $validatedData['user_ids'];
        $eventId = $event->id;
        $amount = $validatedData['amount'];
        $assignedBy = Auth::id();

        $storedUsers = [];
        $skippedUsers = [];

        DB::transaction(function () use ($userIds, $eventId, $amount, $assignedBy, &$storedUsers, &$skippedUsers) {
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
                $alreadyAssigned = Credit::where('user_id', $userId)
                    ->where('event_id', $eventId)
                    ->exists();

                if ($alreadyAssigned) {
                    $skippedUsers[] = [
                        'user_id' => $userId,
                        'reason' => 'Credit already assigned',
                    ];
                    continue;
                }

                // Create new credit
                Credit::create([
                    'user_id' => $userId,
                    'event_id' => $eventId,
                    'assigned_by' => $assignedBy,
                    'amount' => $amount,
                ]);

                $storedUsers[] = $userId;
            }
        });

        return response()->json([
            'message' => 'Processed credit storage for multiple users.',
            'stored_users' => $storedUsers,
            'skipped_users' => $skippedUsers,
        ], count($storedUsers) > 0 ? 201 : 409); // 201 if at least one stored, else conflict
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        Gate::authorize('view', Credit::class);

        $credit = Credit::with('event')->find($id);

        if (!$credit) {
            return response()->json([
                'status' => 404,
                'message' => 'Credit not found.',
            ], 404);
        }


        return response()->json([
            'message' => 'Credit retrived successfully',
            'credit' => $credit,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreditRequest $request, Event $event)
    {
        Gate::authorize('update_credit', $event);

        $validatedData = $request->validated();

        $userId = $validatedData['user_id'];
        $eventId = $event->id; // Use the injected Event model

        // Check if the user is registered for the event
        if (!EventRegistration::where('user_id', $userId)->where('event_id', $eventId)->exists()) {
            return response()->json([
                'message' => 'User is not registered for the event.',
            ], 403);
        }

        // Retrieve the credit record
        $credit = Credit::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->first();

        if (!$credit) {
            return response()->json([
                'message' => 'Credit not found for the user and event.',
            ], 404);
        }

        if ($credit->amount == $validatedData['amount']) {
            return response()->json([
                'message' => 'No update performed. Same amount submitted.',
            ], 200);
        }

        // Update and save the credit
        $credit->fill([
            'amount' => $validatedData['amount'],
        ]);
        $credit->save();

        return response()->json([
            'message' => 'Credits updated successfully.',
            'credit' => $credit,
        ], 200);
    }


    public function updateMultiple(CreditRequest $request, Event $event)
    {
        Gate::authorize('update_credit', $event);

        $validatedData = $request->validated();
        $usersIds = $validatedData['usersIds'];

        $updatedUsers = [];
        $unupdatedUsers = [];

        foreach ($usersIds as $userId) {
            $user = User::find($userId);

            if ($user && $this->validRegisteration($user->id, $event->id)) {
                $credit = Credit::where('user_id', $user->id)
                    ->where('event_id', $event->id)
                    ->first();

                if ($credit) {
                    $credit->update([
                        'amount' => $validatedData['amount'],
                    ]);
                    $updatedUsers[] = $user->id;
                } else {
                    $unupdatedUsers[] = $user->id;
                }
            } else {
                $unupdatedUsers[] = $userId; // Either user doesn't exist or not valid registration
            }
        }

        return response()->json([
            'message' => 'Processed users',
            'updated_users' => $updatedUsers,
            'not_updated_users' => $unupdatedUsers,
        ]);
    }


    private function validRegisteration($userId, $eventId): bool
    {
        if (!EventRegistration::where('user_id', $userId)->where('event_id', $eventId)->exists()) {
            return false;
        }
        return true;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Gate::authorize('delete', Credit::class);

        $credit = Credit::findOrFail($id);

        if (!$credit) {
            return response()->json([
                'status' => 404,
                'message' => 'Credit not found.',
            ], 404);
        }

        $credit->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Event deleted successfully.',
        ]);
    }

    /**
     * Get credits of current authenticated user
     */
    public function getUserCredits()
    {
        Gate::authorize('getUserCredits', Credit::class);

        $userId = Auth::id();

        $credits = Credit::with('event  ')
            ->where('user_id', $userId)
            ->get();

        return response()->json([
            'credits' => $credits,
        ], 200);
    }

    /**
     * Get the details of a single credit record
     */
    public function showUserCreditsDetails(string $id)
    {
        $userId = Auth::id();

        $credit = Credit::with('event')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        Gate::authorize('showUserCreditsDetails', $credit);

        if ($credit) {
            return response()->json([
                'message' => 'Retrived successfully',
                'credits' => $credit,
            ], 200);
        }

        return response()->json([
            'message' => 'Not records found',
        ], 404);
    }
}
