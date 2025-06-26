<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use Auth;
use Illuminate\Support\Arr;
use Exception;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EventController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $events = DB::table('events')->orderBy('updated_at')->paginate(20);

        if ($events->isEmpty()) {
            return response()->json([
                'message' => 'No events found!',
            ]);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Events retrieved successfully',
            'events' => $events,
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEventRequest $request)
    {
        Gate::authorize('create', Event::class);

        try {

            Log::info("Attempt to create a event", [
                'user_id',
                Auth::id(),
            ]);

            $randomUuid = Str::uuid();
            $validated = $request->validated();

            $validatedData = Arr::add($validated, 'uuid', $randomUuid);

            DB::beginTransaction();

            // creates an event
            $event = $request->user()->events()->create($validatedData);
            DB::commit();

            if ($event) {

                // clear caches

                Log::info('Event successfully created', ['user_id' => Auth::id()]);
            }

            return $this->respondSuccess($event, 'Event created successfully', 201);
        } catch (Validator $validator) {

            DB::rollBack();
            return $this->respondError('Event creation failed', 500, $validator);
        } catch (Exception $e) {

            DB::rollBack();
            return $this->respondError('Event creation failed', 500, $e->getMessage());
        }
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'status' => 404,
                'message' => 'Event not found.',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Event retrieved successfully.',
            'data' => $event,
        ], 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventRequest $request, string $id)
    {
        $event = Event::find($id);

        Gate::authorize('update', $event);

        if (!$event) {
            return response()->json([
                'status' => 404,
                'message' => 'Event not found.',
            ], 404);
        }

        $validated = $request->validated();

        // Prevent user_id from being changed
        unset($validated['user_id']);

        if (!$event->update($validated)) {
            throw new \Exception('Event not updated');
        }

        return response()->json([
            'message' => 'Event Updated Successfully',
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $event = Event::findOrFail($id);

        Gate::authorize('delete', $event);

        $event->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Event deleted successfully.',
        ]);
    }

    /**
     *
     */
    public function myEvents()
    {
        $events = Event::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->simplePaginate(20);

        if ($events->isEmpty()) {
            return $this->respondError('No events found', 404);
        }

        return $this->respondSuccess($events, 'Events retrieved successfully', 200);
    }
}
