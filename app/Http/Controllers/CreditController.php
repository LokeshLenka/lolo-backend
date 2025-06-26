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
use App\Http\Requests\UpdateCreditRequest;
use App\Models\EventRegistration;
use Exception;
use Illuminate\Contracts\Support\ValidatedData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\CreditResource;
use App\Services\CreditManagerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Str;

class CreditController extends Controller
{
    public function __construct(private CreditManagerService $creditManagerService) {}
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', Credit::class);

        if (Cache::has('index_credits')) {
            return response()->json(
                Cache::get('index_credits')
            );
        }

        $credits = Credit::with('user:id,username', 'event:id,uuid,name')->simplePaginate(20);

        Cache::add('index_credits', $credits, 60);

        // return CreditResource::collection($credits);
        return response()->json([$credits]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreditRequest $request, string $uuid)
    {
        $event = Event::where('uuid', $uuid)->first();

        if (!$event) {
            throw new Exception("Event not found");
        }

        try {
            return $this->creditManagerService->store($request->validated(), $event);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function storeMultiple(CreditRequest $request, string $uuid)
    {
        $event = Event::where('uuid', $uuid)->first();

        if (!$event) {
            throw new Exception("Event not found");
        }

        try {
            return $this->creditManagerService->storeMultiple($request->validated(), $event);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ]);
        }
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
    public function update(UpdateCreditRequest $request,  string $eventUuid, string $creditUuid)
    {
        $event = Event::where('uuid', $eventUuid)->first();

        if (!$event) {
            throw new Exception("Event not found");
        }

        $credit = Credit::where('uuid', $creditUuid)->first();

        if (!$credit) {
            throw new Exception("Credit not found");
        }

        try {
            return $this->creditManagerService->update($request->validated(), $event, $credit);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ]);
        }
    }


    public function updateMultiple(UpdateCreditRequest $request, string $eventUuid)
    {
        $event = Event::where('uuid', $eventUuid)->first();

        if (!$event) {
            throw new Exception("Event not found");
        }
        try {
            return $this->creditManagerService->updateMultiple($request->validated(), $event);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ]);
        }
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
