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
use Illuminate\Support\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EventController extends Controller
{

    public function __construct(private EventService $eventService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $events = Event::with([
        //     'images',
        // ])->orderBy('created_at', 'asc')
        //     ->paginate($request->get('per_page', 10));


        // if ($events->isEmpty()) {
        //     return response()->json([
        //         'message' => 'No events found!',
        //     ]);
        // }

        // return response()->json([
        //     'status' => 200,
        //     'message' => 'Events retrieved successfully',
        //     'events' => $events,
        // ]);

        $perPage = $request->input('per_page', 20);


        try {
            $events = Event::addSelect([
                'cover_image' => Image::select('path') // FIXED: 'url' -> 'path'
                    // FIXED: Table name 'event_images' (plural)
                    ->join('event_images', 'images.id', '=', 'event_images.image_id')
                    ->whereColumn('event_images.event_id', 'events.id')
                    // FIXED: Order by 'created_at' because pivot has no 'id' column
                    ->orderBy('event_images.created_at', 'asc')
                    ->limit(1)
            ])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // OPTIONAL: If 'path' is relative (e.g., 'uploads/img.jpg') and you need a full URL,
            // you might need to append the storage path manually here.
            // transform() runs on the results, not the DB, so it's safe.
            $events->getCollection()->transform(function ($event) {
                if ($event->cover_image) {
                    // Converts 'public/uploads/img.jpg' -> 'http://localhost:8000/storage/uploads/img.jpg'
                    $event->cover_image = asset('storage/' . $event->cover_image);
                }
                return $event;
            });
            return $this->respondSuccess($events, "Events retrived Successfully", 200);
        } catch (Exception $e) {
            $this->logError("Failed to retrive events data", $e);
            return $this->respondError("Failed to retrive events data", 404, $e->getMessage());
        }
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

            DB::beginTransaction();

            $eventUuid = (string) Str::uuid();
            $validated = $request->validated();

            // Format dates (IST)
            $validated['start_date'] = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $validated['start_date'],
                'Asia/Kolkata'
            );

            $validated['end_date'] = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $validated['end_date'],
                'Asia/Kolkata'
            );

            $validated['registration_deadline'] = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $validated['registration_deadline'],
                'Asia/Kolkata'
            );

            $validated['uuid'] = $eventUuid;

            /*
        |--------------------------------------------------------------------------
        | Store QR Code in Storage (NOT in DB)
        |--------------------------------------------------------------------------
        */
            if ($request->hasFile('qr_code')) {

                $file = $request->file('qr_code');

                $filename = 'qr_image.' . $file->getClientOriginalExtension();

                $path = $file->storeAs(
                    "events/{$eventUuid}/qr",
                    $filename,
                    'public'
                );

                $validated['qr_code_path'] = $path;
            }

            // Create Event
            $event = $request->user()->events()->create($validated);

            /*
        |--------------------------------------------------------------------------
        | Store Event Images
        |--------------------------------------------------------------------------
        */
            if ($request->hasFile('images')) {

                foreach ($request->file('images') as $image) {

                    $filename = Str::uuid() . '.' . $image->getClientOriginalExtension();

                    $path = $image->storeAs(
                        "events/{$event->uuid}",
                        $filename,
                        'public'
                    );

                    $imageModel = Image::create([
                        'uuid' => Str::uuid(),
                        'uploaded_by' => Auth::id(),
                        'path' => $path,
                        'img_type' => 'event',
                        'alt_txt' => $request->input('alt_txt', $event->name),
                    ]);

                    $event->images()->attach($imageModel->id);
                }
            }

            DB::commit();

            Log::info('Event successfully created', [
                'user_id' => Auth::id(),
                'event_id' => $event->id,
                'event_uuid' => $event->uuid,
            ]);

            $event->load('images');

            return $this->respondSuccess(
                new EventResource($event),
                'Event created successfully',
                201
            );
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Event creation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return $this->respondError(
                'Event creation failed',
                500,
                config('app.debug') ? $e->getMessage() : 'Internal server error'
            );
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
                'validated_keys' => array_keys($validated),
                'qr_code' => $request->hasFile('qr_code'),
            ]);

            if ($request->hasFile('qr_code')) {

                $file = $request->file('qr_code');

                $filename = 'qr_image.' . $file->getClientOriginalExtension();

                $path = $file->storeAs(
                    "events/{$event->uuid}/qr",
                    $filename,
                    'public'
                );

                $validated['qr_code_path'] = $path;
            }

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
    public function myEvents(Request $request)
    {
        $perPage = $request->input('per_page', 20);

        $events = Event::where('user_id', Auth::id())
            ->addSelect([
                'cover_image' => Image::select('path') // FIXED: 'url' -> 'path'
                    // FIXED: Table name 'event_images' (plural)
                    ->join('event_images', 'images.id', '=', 'event_images.image_id')
                    ->whereColumn('event_images.event_id', 'events.id')
                    // FIXED: Order by 'created_at' because pivot has no 'id' column
                    ->orderBy('event_images.created_at', 'asc')
                    ->limit(1)
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // OPTIONAL: If 'path' is relative (e.g., 'uploads/img.jpg') and you need a full URL,
        // you might need to append the storage path manually here.
        // transform() runs on the results, not the DB, so it's safe.
        $events->getCollection()->transform(function ($event) {
            if ($event->cover_image) {
                // Converts 'public/uploads/img.jpg' -> 'http://localhost:8000/storage/uploads/img.jpg'
                $event->cover_image = asset('storage/' . $event->cover_image);
            }
            return $event;
        });

        return $this->respondSuccess($events, 'Events retrieved successfully', 200);
    }
}
