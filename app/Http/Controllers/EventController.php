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

class EventController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $events = Event::with([
            'images',
        ])->orderBy('created_at', 'asc')
            ->paginate($request->get('per_page', 15));


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
                    $path = $image->storeAs("events/{$event->uuid}/", $filename, 'public');

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
    public function update(UpdateEventRequest $request, string $uuid)
    {

        \Log::info('Raw request content:', ['content' => $request->getContent()]);


        $event = Event::where('uuid', $uuid)->first();

        // return response()->json([$event]); no error here

        Gate::authorize('update', $event);

        if (!$event) {
            return response()->json([
                'status' => 404,
                'message' => 'Event not found.',
            ], 404);
        }

        $validated = $request->validated();

        // return response()->json(["validated" => $request->validated(), "input" => $request->input()]);

        Log::info("validated data to update an event", [$request->input(), $uuid, $event]);


        // Prevent user_id from being changed
        unset($validated['user_id']);

        if (!$event->update($validated)) {
            throw new \Exception('Event not updated');
        }


        // return response()->json([$request]);

        $event = Event::with('images')->where('uuid', $uuid)->first();

        Gate::authorize('update', $event);

        if (!$event) {
            return response()->json([
                'status' => 404,
                'message' => 'Event not found.',
            ], 404);
        }

        $validated = $request->validated();
        unset($validated['user_id']); // Prevent user_id from being changed

        Log::info("validated data to update an event", [$request->input(), $uuid, $event]);

        return response()->json([$validated, $request]);

        try {
            DB::beginTransaction();



            // Flag to track if any changes were made
            $hasChanges = false;

            // Update event details if provided
            if (!empty($validated) && $event->fill($validated)->isDirty()) {
                $event->save();
                $hasChanges = true;
                Log::info('Event details updated', [
                    'event_id' => $event->id,
                    'user_id' => Auth::id()
                ]);
            }

            /**
             * Handle image deletions
             */
            $imagesToDeleteInput = $request->input('images_to_delete', []);

            if (!empty($imagesToDeleteInput) && is_array($imagesToDeleteInput)) {
                $imagesToDelete = Image::whereIn('uuid', $imagesToDeleteInput)
                    ->whereHas('events', function ($query) use ($event) {
                        $query->where('event_id', $event->id);
                    })->get();

                if ($imagesToDelete->isNotEmpty()) {
                    foreach ($imagesToDelete as $image) {
                        // Delete file from storage
                        if (Storage::disk('public')->exists($image->path)) {

                            Log::info('Attempt to delete images');
                            Storage::disk('public')->delete($image->path);
                        }

                        Log::info('succesfully deleted images');

                        // Detach from event and delete record
                        $event->images()->detach($image->id);
                        $image->delete();
                    }

                    $hasChanges = true;
                    Log::info('Images deleted successfully', [
                        'event_id' => $event->id,
                        'deleted_count' => $imagesToDelete->count(),
                        'user_id' => Auth::id()
                    ]);
                }
            }

            /**
             * Handle image uploads if present
             */
            if ($request->hasFile('images')) {

                // If replace_images is true, delete existing images before adding new ones
                if ($request->boolean('replace_images')) {
                    foreach ($event->images as $image) {
                        // Delete file from storage
                        if (Storage::disk('public')->exists($image->path)) {
                            Storage::disk('public')->delete($image->path);
                        }

                        // Detach and delete record
                        $event->images()->detach($image->id);
                        $image->delete();
                    }

                    Log::info('All existing images replaced', [
                        'event_id' => $event->id,
                        'user_id' => Auth::id()
                    ]);
                }

                // Add new images
                foreach ($request->file('images') as $image) {
                    // Generate a unique filename with original extension
                    $filename = Str::uuid() . '.' . $image->getClientOriginalExtension();

                    // Store with consistent path structure (same as in store method)
                    $path = $image->storeAs("events/{$event->uuid}/", $filename, 'public');

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
                        $hasChanges = true;
                    } else {
                        throw new \Exception('Failed to save image for event during update.');
                    }
                }

                Log::info('New images uploaded successfully', [
                    'event_id' => $event->id,
                    'uploaded_count' => count($request->file('images')),
                    'user_id' => Auth::id()
                ]);
            }

            // Only commit if there were actual changes
            if ($hasChanges) {
                DB::commit();

                // Reload with updated relationships
                $event->load('images');

                return response()->json([
                    'status' => 200,
                    'message' => 'Event updated successfully',
                    'data' => $event
                ], 200);
            } else {
                DB::rollBack();

                return response()->json([
                    'status' => 200,
                    'message' => 'No changes detected',
                    'data' => $event
                ], 200);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Event update failed', [
                'event_id' => $event->id ?? null,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->respondError('Event update failed', 500, $e->getMessage());
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
