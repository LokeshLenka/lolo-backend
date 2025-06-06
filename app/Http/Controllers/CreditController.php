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
use Exception;
use Illuminate\Support\Facades\DB;

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

        return response()->json([
            'credits' => $credits,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreditRequest $request)
    {
        Gate::authorize('create', Credit::class);

        $validatedData = $request->validated();

        return DB::transaction(function () use ($validatedData) {
            $alreadyAssigned = Credit::where('user_id', $validatedData['user_id'])
                ->where('event_id', $validatedData['event_id'])
                ->exists();

            if ($alreadyAssigned) {
                return response()->json([
                    'message' => 'Credits are already assigned to the user.',
                ], 409); // Conflict
            }

            $credit = Credit::create([
                'user_id' => $validatedData['user_id'],
                'event_id' => $validatedData['event_id'],
                'assigned_by' => Auth::id(),
                'amount' => $validatedData['amount'],
            ]);

            return response()->json([
                'credit' => $credit,
            ], 201); // Created
        });
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
    public function update(CreditRequest $request, string $id)
    {
        $event = Event::findOrFail($id)->first();

        Gate::authorize('update', $event);

        $validatedData = $request->validated();

        $event->fill($validatedData);
        $event->save();
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
    public function getMyCredits()
    {

        Gate::authorize('get_my_credits', Credit::class);

        $userId = Auth::id();

        $credits = Credit::with('event')
            ->where('user_id', $userId)
            ->get();

        return response()->json([
            'credits' => $credits,
        ], 200);
    }

    /**
     * Get the details of a single credit record
     */
    public function showCreditsDetails(string $id)
    {
        $userId = Auth::id();

        $credit = Credit::with('event')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

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
