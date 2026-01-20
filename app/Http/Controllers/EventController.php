<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Image;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use Auth;
use Illuminate\Support\Arr;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\EventService;

class EventController extends Controller
{

    public function __construct(private EventService $eventService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $events = Event::with([
            'images',
        ])->orderBy('created_at', 'asc')
            ->paginate($request->get('per_page', 10));


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
            Log::info("Attempt to create an event", [
                'user_id' => Auth::id(),
            ]);

            $randomUuid = Str::uuid();
            $validated = $request->validated();
            $validatedData = Arr::add($validated, 'uuid', $randomUuid);

            Log::info('Validated Event details to store', $validated);

            DB::beginTransaction();

            // Create the event
            $event = $request->user()->events()->create($validatedData);

            // Handle image uploads if present
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {

                    // Generate a unique filename with original extension
                    $filename = Str::uuid() . '.' . $image->getClientOriginalExtension();
                    $path = $image->storeAs("events/{$event->uuid}", $filename, 'public');

                    // Create image record
                    $imageModel = new Image([
                        'uuid' => Str::uuid(),
                        'uploaded_by' => Auth::id(),
                        'path' => $path,
                        'img_type' => 'event',
                        'alt_txt' => $request->input('alt_txt', $event->name)
                    ]);

                    if ($imageModel->save()) {
                        // Associate image with event only if image was saved successfully
                        $event->images()->attach($imageModel->id);
                    } else {
                        throw new \Exception('Failed to save image for event.');
                    }
                }
            }

            DB::commit();

            if ($event) {
                Log::info('Event successfully created', [
                    'user_id' => Auth::id(),
                    'event_id' => $event->id
                ]);
            }

            // Load the images relationship
            $event->load('images');

            return $this->respondSuccess($event, 'Event created successfully', 201);
        } catch (Validator $validator) {
            DB::rollBack();
            return $this->respondError('Event creation failed', 500, $validator);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Event creation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return $this->respondError('Event creation failed', 500, $e->getMessage());
        }
    }



    /**
     * Display the specified resource.
     */
    public function show(string $uuid)
    {
        // FIX: Eager load coordinators AND their possible profiles
        $event = Event::with([
            'images',
            'coordinatorOneUser.managementProfile',
            'coordinatorOneUser.musicProfile',
            'coordinatorTwoUser.managementProfile',
            'coordinatorTwoUser.musicProfile',
            'coordinatorThreeUser.managementProfile',
            'coordinatorThreeUser.musicProfile',
        ])->where('uuid', $uuid)->first();

        if (!$event) {
            return response()->json([
                'status' => 404,
                'message' => 'Event not found.',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Event retrieved successfully.',
            'data' => new EventResource($event),
        ], 200);
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventRequest $request, $uuid)
    {
        try {
            $event = Event::where('uuid', $uuid)->firstOrFail();

            // Get validated data
            $validated = $request->validated();

            // Log incoming request for debugging
            Log::info('Update event request received', [
                'uuid' => $uuid,
                'has_files' => $request->hasFile('images'),
                'file_count' => $request->hasFile('images') ? count($request->file('images')) : 0,
                'validated_keys' => array_keys($validated)
            ]);

            // Separate event attributes from image options
            $imageRelatedKeys = ['images', 'replace_images', 'images_to_delete', 'alt_txt'];
            $eventAttributes = array_diff_key($validated, array_flip($imageRelatedKeys));

            // Prepare image options
            $imageOptions = [
                'images' => $request->hasFile('images') ? $request->file('images') : [],
                'replace_images' => $request->input('replace_images', false),
                'images_to_delete' => $request->input('images_to_delete', []),
                'alt_txt' => $request->input('alt_txt', $event->name)
            ];

            // Update the event
            $hasChanges = $this->eventService->updateEvent($event, $eventAttributes, $imageOptions);

            // Reload event with images
            $event->refresh()->load('images');

            if (!$hasChanges) {
                return response()->json([
                    'status' => 200,
                    'message' => 'No changes detected',
                    'data' => [
                        'uuid' => $event->uuid,
                        'name' => $event->name,
                        'images' => $event->images
                    ]
                ], 200);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Event updated successfully',
                'data' => [
                    'uuid' => $event->uuid,
                    'name' => $event->name,
                    'images' => $event->images,
                    'images_count' => $event->images->count()
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Event not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Event update failed', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Event update failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $event = Event::with('images')->findOrFail($id);

        Gate::authorize('delete', $event);

        try {
            DB::beginTransaction();

            // Delete associated images from storage
            foreach ($event->images as $image) {
                Storage::disk('public')->delete($image->path);
                $image->delete();
            }

            // The event_images records will be automatically deleted due to cascade
            $event->delete();

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Event and associated images deleted successfully.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->respondError('Event deletion failed', 500, $e->getMessage());
        }
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
