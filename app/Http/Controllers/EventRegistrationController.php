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
use App\Http\Requests\EventRequest;
use App\Http\Requests\UpdateEventRegistration;
use App\Models\Credit;
use Carbon\Carbon;
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
    public function indexAllRegistrations(?string $eventType = null)
    {
        Gate::authorize('viewAll', EventRegistration::class);

        // If no event type is passed, fetch all with pagination
        if (is_null($eventType)) {

            // Fetch all registrations with user info
            $registrations = EventRegistration::with('user:id,username') // Eager load user
                ->whereNull('deleted_at')
                ->orderBy('updated_at')
                ->paginate(20);
        } else {

            // Fetch only registrations for the given event type, excluding public
            $registrations = EventRegistration::with('user:id,username') // Eager load user
                ->whereHas('event', function ($query) use ($eventType) {
                    $query->where('type', $eventType)
                        ->where('type', '!=', EventType::Public->value);
                })
                ->orderBy('registered_at', 'desc')
                ->paginate(20);
        }


        if ($registrations->isEmpty()) {
            return response()->json([
                'message' => 'No registrations found for this event type.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'message' => 'Registrations fetched successfully.',
            'user_id' => Auth::user()->getUserName(),
            'data' => $registrations,
        ]);
    }

    public function indexAllClubRegistrations()
    {
        return $this->indexAllRegistrations(EventType::ClubMembersOnly->value);
    }

    public function indexAllMemberRegistrations()
    {
        return $this->indexAllRegistrations(EventType::MusicMembersOnly->value);
    }

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


    public function updateMemberRegistration(UpdateEventRegistration $request, EventRegistration $eventRegistration): JsonResponse
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


    public function destroyMemberRegistration(EventRegistration $eventRegistration)
    {
        return $this->destroyRegistration($eventRegistration);
    }


    private function showRegistration(EventRegistration $eventRegistration, ?string $eventType = null)
    {
        Gate::authorize('viewAny', EventRegistration::class);

        $registration = EventRegistration::find($eventRegistration)->first();

        if (!$registration) {
            return response()->json(['message' => 'Registration not found.'], 404);
        }

        return response()->json(['data' => $registration], 200);
    }

    public function showClubRegistration(EventRegistration $eventRegistration)
    {
        return $this->showRegistration($eventRegistration, EventType::ClubMembersOnly->value);
    }

    public function showMemberRegistration(EventRegistration $eventRegistration)
    {
        return $this->showRegistration($eventRegistration, EventType::MusicMembersOnly->value);
    }


    private function showRegistrationsByEvent(Event $event, ?string $eventType = null)
    {
        Gate::authorize('viewAny', EventRegistration::class); {
            if (is_null($eventType)) {

                // Fetch all registrations with user info
                $registration = EventRegistration::with('user:id,username', 'event:id,type') // Eager load user
                    ->where('event_id', $event->id)
                    ->whereNull('deleted_at')
                    ->orderBy('updated_at')
                    ->paginate(20);
            } else {

                $registration = EventRegistration::whereHas('event', function ($q) use ($eventType) {
                    $q->where('type', $eventType)
                        ->where('type', '!=', EventType::Public->value);
                })
                    ->where('event_id', $event->id)
                    ->first();
            }
        }

        if (!$registration) {
            return response()->json(['message' => 'Registration not found.'], 404);
        }

        return response()->json(['data' => $registration], 200);
    }

    public function showClubRegistrationsByEvent(Event $event)
    {
        return $this->showRegistrationsByEvent($event, EventType::ClubMembersOnly->value);
    }

    public function showMemberRegistrationsByEvent(Event $event)
    {
        return $this->showRegistrationsByEvent($event, EventType::MusicMembersOnly->value);
    }




    /**
     *
     * for users
     *
     *
     */


    private function indexUserRegistrations(string $eventType)
    {
        $registrations = EventRegistration::whereHas('event', function ($q) use ($eventType) {
            $q->where('type', $eventType)
                ->where('type', '!=', EventType::Public->value);
        })
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        if ($registrations->isEmpty()) {
            return response()->json([
                'message' => 'You haven`t registered for any events yet.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'message' => 'Your registrations were fetched successfully.',
            'data' => $registrations,
        ]);
    }

    public function indexUserClubRegistrations()
    {
        Gate::authorize('viewClubRegistrations', EventRegistration::class);

        return $this->indexUserRegistrations(EventType::ClubMembersOnly->value);
    }

    public function indexUserMemberRegistrations()
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
        $qrBase64 = $this->eventRegistrationService->generateQrCode($ticketCode);

        return response()->json([
            'message'     => 'Registration successful',
            'ticket_code' => $ticketCode,
            'qr_image'    => 'data:image/png;base64,' . $qrBase64,
        ]);
    }




    private function storeRegistration(User $user, Event $event, array $validated): string
    {
        return DB::transaction(function () use ($user, $event, $validated) {
            $ticketCode = 'LOLO-' . strtoupper(Str::uuid());

            $registration = EventRegistration::firstOrCreate(
                [
                    'user_id' => $user->getUserName(),
                    'event_id' => $event->id,
                ],
                [
                    'ticket_code' => $ticketCode,
                    'registered_at' => Carbon::now(),
                    'registration_status' => $validated['registration_status'] ?? 'pending',
                    'is_paid' => $validated['is_paid'] ?? false,
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

    public function storeMemberRegistration(StoreEventRegistration $request)
    {
        Gate::authorize('storeMusicRegistrations', EventRegistration::class);

        return $this->handleStore($request, 'members');
    }


    private function showUserRegistration(EventRegistration $eventRegistration, string $eventType)
    {
        $eventRegistration = EventRegistration::where('user_id', Auth::id())->where('id', $eventRegistration->id)->first();

        if (!$eventRegistration) {
            return response()->json(['message' => 'Registration not found']);
        }

        return response()->json(['data' => $eventRegistration], 200);
    }

    public function showUserClubRegistration(EventRegistration $eventRegistration)
    {
        Gate::authorize('showUserClubRegistration', $eventRegistration);

        return $this->showUserRegistration($eventRegistration, EventType::ClubMembersOnly->value);
    }


    public function showUserMemberRegistration(EventRegistration $eventRegistration)
    {
        Gate::authorize('showUserMemberRegistration', $eventRegistration);

        return $this->showUserRegistration($eventRegistration, EventType::MusicMembersOnly->value);
    }

}
