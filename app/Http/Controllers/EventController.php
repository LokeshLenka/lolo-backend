<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\EventRequest;
use App\Policies\EventPolicy;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

use function PHPUnit\Framework\isEmpty;

class EventController extends Controller
{
    use SoftDeletes;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $events = DB::table('events')->orderBy('updated_at')->simplePaginate(15);

        if ($events->isEmpty()) {
            return response()->json([
                'status' => 204,
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
    public function store(EventRequest $request)
    {
        Gate::authorize('create', Event::class);

        $event = $request->user()->events()->create($request->validated());

        return response()->json([
            'message' => 'Event created successfully.',
            'event' => $event,
        ], 201);
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
    public function update(EventRequest $request, string $id)
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

        $event->update($validated);

        return response()->json([
            'status' => 200,
            'message' => 'Event updated successfully.',
            'data' => $event,
        ]);
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
}
