<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use App\Http\Requests\StoreEventRegistration;
use App\Services\EventRegistrationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Enums\EventType;
use App\Enums\IsPaid;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Http\Requests\EventRequest;
use App\Http\Requests\UpdateEventRegistration;
use App\Models\Credit;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Event Registration Controller
 *
 * This controller manages all aspects of event registrations within the application.
 * It provides functionality for users to register for events, view their registrations,
 * and for administrators to manage all registrations.
 *
 * Features:
 * - User registration for club and member events
 * - Registration eligibility validation
 * - QR code generation for tickets
 * - Registration CRUD operations
 * - Admin views for different event types
 * - Transaction-safe registration creation
 * - Pagination support for large datasets
 *
 * @package App\Http\Controllers
 * @author Your Name
 * @version 1.1
 * @since 2024-01-01
 */
class EventRegistrationController extends Controller
{
    /**
     * Constructor - Inject the EventRegistrationService dependency
     *
     * Initializes the controller with required dependencies using property promotion.
     *
     * @param EventRegistrationService $eventRegistrationService Service for handling registration logic and QR code generation
     */
    public function __construct(
        private EventRegistrationService $eventRegistrationService
    ) {}

    //for admin
    public function indexAllRegistrations(Request $request, ?string $eventType = null)
    {
        // Gate::authorize('viewAll', EventRegistration::class);

        if (Cache::has('index_registrations')) {
            return response()->json(
                Cache::get('index_registrations')
            );
        }

        $registrations = EventRegistration::query()
            ->when($request->has('type'), fn($q) =>

            $q->type($request->type))
            ->when($request->has('created_by'), fn($q) => $q->createdBy($request->created_by))
            ->when($request->has('event_id'), fn($q) => $q->eventId($request->event_id))
            ->when($request->has('registration_status'), fn($q) => $q->registrationStatus($request->registration_status))
            ->when($request->has('payment_status'), fn($q) => $q->paymentStatus($request->payment_status))
            ->when($request->has('is_paid'), fn($q) => $q->isPaid($request->is_paid))
            // ->when($request->has('type'), fn($q) => $q->type($request->type))
            // ->when($request->has('type'), fn($q) => $q->type($request->type))
            // ->when($request->has('type'), fn($q) => $q->type($request->type))
            // ->when($request->has('type'), fn($q) => $q->type($request->type))
            // ->when($request->has('type'), fn($q) => $q->type($request->type))
            // ->when($request->has('active'), fn($q) => $q->active($request->boolean('active')))
            ->with('user:id,username,role,promoted_role,is_approved', 'event:id,type') // include related user info
            ->paginate(20);

        Cache::add('index_registrations', $registrations, 60);


        // If no event type is passed, fetch all with pagination
        // if (is_null($eventType)) {

        //     // Fetch all registrations with user info
        //     $registrations = EventRegistration::with('user:id,username') // Eager load user
        //         ->whereNull('deleted_at')
        //         ->orderBy('updated_at')
        //         ->paginate(20);
        // } else {

        //     // Fetch only registrations for the given event type, excluding public
        //     $registrations = EventRegistration::with('user:id,username') // Eager load user
        //         ->whereHas('event', function ($query) use ($eventType) {
        //             $query->where('type', $eventType)
        //                 ->where('type', '!=', EventType::Public->value);
        //         })
        //         ->orderBy('registered_at', 'desc')
        //         ->paginate(20);
        // }


        if ($registrations->isEmpty()) {
            return response()->json([
                'message' => 'No registrations found for this event type.',
                'data' => [],
            ], 404);
        }

        return response()->json(
            // 'message' => 'Registrations fetched successfully.',
            // 'user_id' => Auth::user()->getUserName(),
            $registrations
        );
    }

    // public function indexAllClubRegistrations()
    // {
    //     return $this->indexAllRegistrations(EventType::ClubMembersOnly->value);
    // }

    // public function indexAllMusicRegistrations()
    // {
    //     return $this->indexAllRegistrations(EventType::MusicMembersOnly->value);
    // }

    private function updateRegistration(UpdateEventRegistration $request, EventRegistration $eventRegistration, string $expectedEventType): JsonResponse
    {
        Gate::authorize('update', $eventRegistration);

        return DB::transaction(function () use ($request, $eventRegistration, $expectedEventType) {
            $validated = $request->validated();

            $event = Event::findOrFail($validated['event_id']);

            if (
                $event->type !== $expectedEventType ||
                $eventRegistration->event_id !== $event->id ||
                $eventRegistration->user_id !== $validated['user_id']
            ) {
                throw new Exception('Invalid event or registration details provided.');
            }

            $eventRegistration->update([
                'is_paid' => $validated['is_paid'],
                'registration_status' => $validated['registration_status'],
                'payment_status' => $validated['payment_status'],
                'payment_reference' => $validated['payment_reference'] ?? 'TXN87654321', // Use default or request value
            ]);

            return response()->json([
                'message' => 'Registration updated successfully.',
                'data' => $eventRegistration,
            ]);
        }, 3); // Retry up to 3 times on deadlock or transaction failure
    }


    public function updateClubRegistration(UpdateEventRegistration $request, EventRegistration $eventRegistration): JsonResponse
    {
        return $this->updateRegistration($request, $eventRegistration, EventType::ClubMembersOnly->value);
    }


    public function updateMusicRegistration(UpdateEventRegistration $request, EventRegistration $eventRegistration): JsonResponse
    {
        return $this->updateRegistration($request, $eventRegistration, EventType::MusicMembersOnly->value);
    }

    private function destroyRegistration(EventRegistration $eventRegistration)
    {
        // Check if user has permission to delete this registration
        Gate::authorize('delete', $eventRegistration);

        try {

            $eventRegistration = EventRegistration::findOrFail($eventRegistration);

            $eventRegistration->delete();
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Something went wrong.'
            ]);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Registration deleted successfully.',
        ]);
    }


    public function destroyClubRegistration(EventRegistration $eventRegistration)
    {
        return $this->destroyRegistration($eventRegistration);
    }


    public function destroyMusicRegistration(EventRegistration $eventRegistration)
    {
        return $this->destroyRegistration($eventRegistration);
    }











    public function showRegistration(EventRegistration $eventRegistration, ?string $eventType = null)
    {
        Gate::authorize('viewAny', EventRegistration::class);

        $registration = EventRegistration::where('uuid', $eventRegistration->uuid)->with('user:id,username', 'event:id,uuid,name')->first();

        if (!$registration) {
            return response()->json(['message' => 'Registration not found.'], 404);
        }

        return response()->json(['data' => $registration], 200);
    }


    public function showRegistrationsByEvent(Event $event)
    {
        Gate::authorize('viewAny', EventRegistration::class);

        $registration = EventRegistration::whereHas('event', function ($q) use ($event) {
            $q->where('uuid', $event->uuid)->with('event:id,name');
            // ->where('type', '!=', EventType::Public->value);
        })->with('user:id,username', 'event:id,uuid,name')
            ->get();

        if (!$registration) {
            return response()->json(['message' => 'Registration not found.'], 404);
        }

        return response()->json(['data' => $registration], 200);
    }















    /**
     *
     * for users
     *
     *
     */


    private function indexUserRegistrations(string $eventType)
    {
        $userId = Auth::id();

        // 1. Define the base query with filters (Event Type & User)
        // We assign this to a variable so we can clone it for stats without rewriting logic.
        $baseQuery = EventRegistration::query()
            ->where('user_id', $userId)
            ->whereHas('event', function ($q) use ($eventType) {
                $q->where('type', $eventType)
                    ->where('type', '!=', EventType::Public->value);
            });

        // 2. Calculate Stats
        // We use (clone $baseQuery) to run separate aggregate counts without affecting the main pagination query.
        $stats = [
            'total_registrations' => (clone $baseQuery)->count(),

            // 'upcoming_events' => (clone $baseQuery)->whereHas('event', function ($q) {
            //     $q->where('start_date', '>', now());
            // })->count(),

            'upcoming_events' => (clone $baseQuery)
                ->where('registration_status', 'confirmed') // Only count confirmed registrations
                ->whereHas('event', function ($q) {
                    $q->where('start_date', '>', now());
                })->count(),


            'completed_events' => (clone $baseQuery)
                ->where('registration_status', 'confirmed') // Only count confirmed registrations
                ->whereHas('event', function ($q) {
                    $q->where('end_date', '<', now());
                })->count(),

            'pending_payments' => (clone $baseQuery)
                ->where('payment_status', PaymentStatus::Pending->value) // Ensure this matches your DB enum/string
                ->count(),
        ];

        // 3. Fetch Paginated Data
        $registrations = $baseQuery
            ->orderBy('created_at', 'desc')
            ->with([
                'event:id,uuid,name,start_date,end_date,status',
                // 'event_images'
            ]) // Added dates/status for frontend context
            ->paginate(20);

        // 4. Return Response
        // returning 200 OK (instead of 404) allows the frontend to receive and display
        // "0" stats when the list is empty, rather than throwing an error.
        return response()->json([
            'message' => $registrations->isEmpty()
                ? 'You haven`t registered for any events yet.'
                : 'Your registrations were fetched successfully.',
            'data' => $registrations,
            'stats' => $stats,
        ]);
    }


    public function indexUserClubRegistrations()
    {
        Gate::authorize('viewClubRegistrations', EventRegistration::class);

        return $this->indexUserRegistrations(EventType::ClubMembersOnly->value);
    }

    public function indexUserMusicRegistrations()
    {
        Gate::authorize('viewMusicRegistrations', EventRegistration::class);

        return $this->indexUserRegistrations(EventType::MusicMembersOnly->value);
    }




    private function handleStore(StoreEventRegistration $request, string $eventType)
    {
        // Get the authenticated user
        $user = User::findOrFail(Auth::id());
        $validated = $request->validated();
        $event = Event::findOrFail($validated['event_id']);

        Cache::add('user', $user, 60);


        // Check if user is eligible for this event type
        if (!$this->eventRegistrationService->isEligible($user, $event, $eventType)) {
            throw new Exception("You are not eligible for this event.");
        }

        // Validate registration constraints (duplicates, deadlines, capacity)
        try {
            $this->eventRegistrationService->validateRegistration($user, $event);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Registration not allowed',
                'reason' => $e->getMessage()
            ], 422); // Unprocessable Entity
        }


        // Create the registration record and get ticket code
        $ticketCode = $this->storeRegistration($user, $event, $validated);

        // Generate QR code for the ticket
        // $qrBase64 = $this->eventRegistrationService->generateQrCode($ticketCode);

        return response()->json([
            'message'     => 'Registration successful',
            'ticket_code' => $ticketCode,
            // 'qr_image'    => 'data:image/png;base64,' . $qrBase64,
        ]);
    }




    private function storeRegistration(User $user, Event $event, array $validated): string
    {
        return DB::transaction(function () use ($user, $event, $validated) {

            $ticketCode = 'LOLO-' . strtoupper(Str::uuid());

            if (
                $validated['is_paid'] === IsPaid::NotPaid->value ||
                $validated['payment_status'] === PaymentStatus::Pending->value ||
                $validated['registration_status'] === RegistrationStatus::PENDING->value
            ) {
                $registrationStatus = RegistrationStatus::PENDING->value;
            }

            $registration = EventRegistration::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'event_id' => $event->id,
                ],
                [
                    'uuid' => Str::uuid(),
                    'ticket_code' => $ticketCode,
                    'registered_at' => Carbon::now(),
                    'registration_status' => $registrationStatus ?? 'confirmed',
                    'is_paid' => $validated['is_paid'] ?? 'not_paid',
                    'payment_status' => $validated['payment_status'] ?? 'pending',
                    'payment_reference' => $validated['payment_reference'] ?? 'TXN-' . strtoupper(Str::random(8)),
                ]
            );

            if (!$registration->wasRecentlyCreated) {
                throw new Exception('You have already registered for this event.');
            }

            return $registration->ticket_code;
        }, 3); // Retry up to 3 times on DB deadlock
    }

    public function storeClubRegistration(StoreEventRegistration $request)
    {
        Gate::authorize('storeClubRegistrations', EventRegistration::class);

        return $this->handleStore($request, 'club');
    }

    public function storeMusicRegistration(StoreEventRegistration $request)
    {
        Gate::authorize('storeMusicRegistrations', EventRegistration::class);

        return $this->handleStore($request, 'music');
    }


    // private function showUserRegistration(EventRegistration $eventRegistration, string $eventType)
    // {
    //     if (!$eventRegistration) {
    //         return response()->json(['message' => 'Registration not found']);
    //     }

    //     return response()->json(['data' => $eventRegistration], 200);
    // }


    private function showUserRegistration(EventRegistration $eventRegistration, string $eventType)
    {
        // 1. Context Validation
        if (!$eventRegistration->relationLoaded('event')) {
            $eventRegistration->load('event');
        }

        if ($eventRegistration->event->type !== $eventType && $eventRegistration->event->type !== EventType::Public->value) {
            return response()->json(['message' => 'Registration not found in this category.'], 404);
        }

        // 2. Load Specific Event Columns Only
        // Instead of loading the entire 'event' model (*), we specify columns.
        // Note: 'id' and 'uuid' are usually required for internal relationship matching,
        // even if not displayed.
        $eventRegistration->load([
            'event',
            // eager load images after
        ]);

        // 3. Clean up the Registration object itself (Optional but recommended)
        // You can hide internal IDs from the main registration object if not needed
        $eventRegistration->makeHidden(['id', 'user_id', 'event_id', 'updated_at']);

        return response()->json([
            'message' => 'Registration details fetched successfully.',
            'data' => $eventRegistration,
        ], 200);
    }




    public function showUserClubRegistration(EventRegistration $eventRegistration)
    {
        Gate::authorize('showUserClubRegistration', $eventRegistration);

        return $this->showUserRegistration($eventRegistration, EventType::ClubMembersOnly->value);
    }


    public function showUserMusicRegistration(EventRegistration $eventRegistration)
    {
        Gate::authorize('showUserMusicRegistration', $eventRegistration);

        return $this->showUserRegistration($eventRegistration, EventType::MusicMembersOnly->value);
    }


    /**
     * Credit - Manager
     */

    public function showRegistrationsByEventWithCredits(string $eventUuid)
    {

        $event = Event::where('uuid', $eventUuid)->first();

        if (!$event) {
            return response()->json([
                'message' => 'Event not found.',
            ], 404);
        }
        $results = [];
        $count = 0;

        $eventRegistrations = EventRegistration::where('event_id', $event->id)
            ->select('uuid', 'event_id', 'user_id', 'registration_status')
            ->with('user:id,username', 'event:id,uuid,name')
            ->get();

        foreach ($eventRegistrations as $eventRegistration) {

            $credit = Credit::where('user_id', $eventRegistration->user_id)->first();

            $results[++$count]['registration'] = $eventRegistration;

            if ($credit) {
                $results[$count]['credit'] = $credit;
            } else {
                $results[$count]['credit'] = null;
            }
        }

        return response()->json(['data' => $results], 200);
    }

    public function showRegistrationWithCredits(string $uuid, ?string $eventType = null)
    {
        Gate::authorize('viewAny', EventRegistration::class);

        $registration = EventRegistration::where('uuid', $uuid)
            ->select('uuid', 'user_id', 'event_id', 'registration_status', 'payment_status')
            ->with('user:id,username', 'event:id,uuid,name')
            ->first();

        $credit = Credit::where('event_id', $registration->event_id)->where('user_id', $registration->user_id)->first();

        $results[]['registration'] = $registration;
        $results[]['credits']  = $credit ?? null;

        if (!$registration) {
            return response()->json(['message' => 'Registration not found.'], 404);
        }

        return response()->json(['data' => $results], 200);
    }
}
