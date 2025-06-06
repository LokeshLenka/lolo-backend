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
use App\Http\Requests\UpdateEventRegistration;

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

    /**
     * Retrieve all registrations without filtering
     *
     * This method fetches all event registrations in the system without any filtering.
     * It's a convenience method that delegates to the main indexRegistrations method.
     *
     * @return \Illuminate\Http\JsonResponse
     *         Returns JSON response with:
     *         - 200: Success with all registrations data
     *         - 404: No registrations found in the system
     *
     * @see indexRegistrations()
     */
    public function indexAllRegistrations()
    {
        return $this->indexRegistrations(null);
    }

    /**
     * Retrieve registrations with optional event type filtering
     *
     * This method serves as the main registration retrieval endpoint. It can either:
     * - Fetch all registrations (when eventType is null) with pagination
     * - Filter registrations by specific event type
     *
     * The method uses different query strategies based on the filter parameter:
     * - Raw DB query with pagination for all registrations
     * - Eloquent relationship query for filtered results
     *
     * @param string|null $eventType Optional event type to filter by (e.g., 'club', 'members')
     *
     * @return \Illuminate\Http\JsonResponse
     *         Returns JSON response with:
     *         - 200: Success with registrations data
     *         - 404: No registrations found for the specified criteria
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException If user lacks viewAny permission
     */
    public function indexRegistrations(?string $eventType)
    {
        // Authorize user to view registrations
        Gate::authorize('viewAny', EventRegistration::class);

        if (is_null($eventType)) {
            // Fetch all registrations with pagination using raw DB query
            $registrations = DB::table('event_registrations')->orderBy('updated_at')->simplePaginate(20);
        } else {
            // Fetch registrations that belong to events of the specified type using Eloquent
            $registrations = EventRegistration::whereHas('event', fn($q) => $q->where('type', $eventType))
                ->orderBy('id')
                ->get();
        }

        // Return 404 if no registrations found
        if ($registrations->isEmpty()) {
            return response()->json([
                'message' => 'No registrations found for this event type.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'message' => 'Registrations fetched successfully.',
            'data' => $registrations,
        ]);
    }

    /**
     * Get all club member registrations for admin view
     *
     * Administrative endpoint to view all registrations for club-only events.
     * This is a convenience method that wraps the generic type-based retrieval.
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing club registrations
     *
     * @see indexRegistrations()
     */
    public function indexClubRegistrationsForAdmin()
    {
        return $this->indexRegistrations(EventType::ClubMembersOnly->value);
    }

    /**
     * Get all music member registrations for admin view
     *
     * Administrative endpoint to view all registrations for music member-only events.
     * This is a convenience method that wraps the generic type-based retrieval.
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing member registrations
     *
     * @see indexRegistrations()
     */
    public function indexMemberRegistrationsForAdmin()
    {
        return $this->indexRegistrations(EventType::MusicMembersOnly->value);
    }

    /**
     * Retrieve all registrations for the currently authenticated user
     *
     * This method returns all event registrations belonging to the currently
     * logged-in user, ordered by registration ID.
     *
     * @return \Illuminate\Http\JsonResponse
     *         Returns JSON response with:
     *         - 200: Success with user's registrations
     *         - 404: No registrations found for the user
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     */
    public function indexAuthenticatedRegistrations()
    {
        // Fetch registrations for the currently logged-in user
        $registrations = EventRegistration::where('user_id', Auth::id())
            ->orderBy('id')
            ->get();

        // Return 404 if user has no registrations
        if ($registrations->isEmpty()) {
            return response()->json([
                'message' => 'No registrations found.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'message' => 'Registrations fetched successfully.',
            'data' => $registrations,
        ]);
    }

    /**
     * Retrieve authenticated user's club event registrations
     *
     * Convenience endpoint for fetching the current user's club registrations.
     * Includes authorization check for viewing club registrations specifically.
     *
     * @return \Illuminate\Http\JsonResponse JSON response with user's club registrations
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException If user lacks viewClubRegistrations permission
     *
     * @see indexAuthenticatedRegistrations()
     */
    public function indexAuthenticatedClubRegistrations()
    {
        // Authorize user to view club registrations
        Gate::authorize('viewClubRegistrations', EventRegistration::class);

        return $this->indexAuthenticatedRegistrations();
    }

    /**
     * Retrieve authenticated user's member event registrations
     *
     * Convenience endpoint for fetching the current user's member registrations.
     * Includes authorization check for viewing music registrations specifically.
     *
     * @return \Illuminate\Http\JsonResponse JSON response with user's member registrations
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException If user lacks viewMusicRegistrations permission
     *
     * @see indexAuthenticatedRegistrations()
     */
    public function indexAuthenticatedMemberRegistrations()
    {
        // Authorize user to view music registrations
        Gate::authorize('viewMusicRegistrations', EventRegistration::class);

        return $this->indexAuthenticatedRegistrations();
    }

    /**
     * Register authenticated user for a club event
     *
     * Creates a new registration for the authenticated user for a club-exclusive event.
     * Validates user eligibility and event constraints before registration.
     *
     * @param StoreEventRegistration $request Validated request containing registration data
     *
     * @return \Illuminate\Http\JsonResponse
     *         Returns JSON response with:
     *         - 200: Registration successful with ticket code and QR code
     *         - 400: Registration failed due to validation errors
     *
     * @throws Exception If user is not eligible or registration constraints are violated
     *
     * @see handleStore()
     */
    public function storeAuthenticatedClubRegistration(StoreEventRegistration $request)
    {
        return $this->handleStore($request, 'club');
    }

    /**
     * Register authenticated user for a member event
     *
     * Creates a new registration for the authenticated user for a member-exclusive event.
     * Validates user eligibility and event constraints before registration.
     *
     * @param StoreEventRegistration $request Validated request containing registration data
     *
     * @return \Illuminate\Http\JsonResponse
     *         Returns JSON response with:
     *         - 200: Registration successful with ticket code and QR code
     *         - 400: Registration failed due to validation errors
     *
     * @throws Exception If user is not eligible or registration constraints are violated
     *
     * @see handleStore()
     */
    public function storeAuthenticatedMemberRegistration(StoreEventRegistration $request)
    {
        return $this->handleStore($request, 'members');
    }

    /**
     * Handle the complete registration process
     *
     * This is the core registration method that:
     * 1. Validates user eligibility for the event type
     * 2. Checks registration constraints (duplicates, deadlines, capacity)
     * 3. Creates the registration record in a database transaction
     * 4. Generates a unique ticket code
     * 5. Creates a QR code for the ticket
     *
     * @param StoreEventRegistration $request Validated request data containing event_id and registration details
     * @param string $expectedType Expected registration type ('club' or 'members')
     *
     * @return \Illuminate\Http\JsonResponse
     *         Success response containing:
     *         - message: Success confirmation
     *         - ticket_code: Unique ticket identifier
     *         - qr_image: Base64 encoded QR code image
     *
     * @throws Exception
     *         - If user is not eligible for the event type
     *         - If user is already registered for the event
     *         - If registration deadline has passed
     *         - If event capacity is full
     *         - If database transaction fails
     *
     * @see isEligible()
     * @see validateRegistration()
     * @see storeRegistration()
     */
    private function handleStore(StoreEventRegistration $request, string $expectedType)
    {
        //Checks whether the current user can store a EventRegistration model
        Gate::authorize('create', EventRegistration::class);

        // Get the authenticated user
        $user = User::findOrFail(Auth::id());
        $validated = $request->validated();
        $event = Event::findOrFail($validated['event_id']);

        // Check if user is eligible for this event type
        if (!$this->isEligible($user, $event, $expectedType)) {
            throw new Exception("You are not eligible for this event.");
        }

        // Validate registration constraints (duplicates, deadlines, capacity)
        $this->validateRegistration($user, $event);

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

    /**
     * Display a specific registration for the authenticated user
     *
     * Retrieves and returns the registration details for a specific event
     * belonging to the currently authenticated user. This is the main method
     * for showing individual registration details.
     *
     * @param Event $event The event model instance to check registration for
     *
     * @return \Illuminate\Http\JsonResponse
     *         Returns JSON response with:
     *         - 200: Registration data found
     *         - 404: No registration found for this user and event
     *
     * @throws \Illuminate\Auth\AuthenticationException If user is not authenticated
     */
    public function showRegistration(Event $event)
    {
        // Find user's registration for this specific event
        $registration = EventRegistration::where('user_id', Auth::id())
            ->where('event_id', $event->id)
            ->first();

        if (!$registration) {
            return response()->json(['message' => 'Registration not found.'], 404);
        }

        return response()->json(['data' => $registration], 200);
    }

    /**
     * Display authenticated user's club registration for a specific event
     *
     * Convenience method for retrieving club event registrations.
     * Currently delegates to the general registration display method.
     *
     * @param Event $event The event to check registration for
     *
     * @return \Illuminate\Http\JsonResponse JSON response with registration data
     *
     * @see showRegistration()
     */
    public function showAuthenticatedClubRegistration(Event $event)
    {
        return $this->showRegistration($event);
    }

    /**
     * Display authenticated user's member registration for a specific event
     *
     * Convenience method for retrieving member event registrations.
     * Currently delegates to the general registration display method.
     *
     * @param Event $event The event to check registration for
     *
     * @return \Illuminate\Http\JsonResponse JSON response with registration data
     *
     * @see showRegistration()
     */
    public function showAuthenticatedMemberRegistration(Event $event)
    {
        return $this->showRegistration($event);
    }

    /**
     * Update an existing event registration
     *
     * Updates registration details for an existing event registration.
     * Authorization is checked via Gate to ensure user has permission.
     * The method validates the request data and updates the registration record.
     *
     * @param UpdateEventRegistration $request Validated HTTP request containing update data
     * @param EventRegistration $eventRegistration The registration model to update
     *
     * @return \Illuminate\Http\JsonResponse
     *         Success response containing:
     *         - message: Update confirmation
     *         - data: Updated registration data
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException If user lacks update permission
     */
    public function updateRegistration(UpdateEventRegistration $request, EventRegistration $eventRegistration)
    {
        // Check if user has permission to update this registration
        Gate::authorize('update', $eventRegistration);

        // Get validated data and update the registration
        $validated = $request->validated();
        $eventRegistration->update($validated);

        return response()->json([
            'message' => 'Registration updated successfully.',
            'data' => $eventRegistration,
        ]);
    }

    /**
     * Update a club event registration
     *
     * Convenience method for updating club-specific registrations.
     * Delegates to the general registration update method.
     *
     * @param UpdateEventRegistration $request Validated request containing update data
     * @param EventRegistration $eventRegistration The club registration to update
     *
     * @return \Illuminate\Http\JsonResponse JSON response with update confirmation
     *
     * @see updateRegistration()
     */
    public function updateClubRegistration(UpdateEventRegistration $request, EventRegistration $eventRegistration)
    {
        return $this->updateRegistration($request, $eventRegistration);
    }

    /**
     * Update a member event registration
     *
     * Convenience method for updating member-specific registrations.
     * Delegates to the general registration update method.
     *
     * @param UpdateEventRegistration $request Validated request containing update data
     * @param EventRegistration $eventRegistration The member registration to update
     *
     * @return \Illuminate\Http\JsonResponse JSON response with update confirmation
     *
     * @see updateRegistration()
     */
    public function updateMemberRegistration(UpdateEventRegistration $request, EventRegistration $eventRegistration)
    {
        return $this->updateRegistration($request, $eventRegistration);
    }

    /**
     * Delete an event registration
     *
     * Removes an event registration from the system after checking authorization.
     * This operation is permanent and cannot be undone.
     *
     * @param EventRegistration $eventRegistration The registration model to delete
     *
     * @return \Illuminate\Http\JsonResponse
     *         Success response confirming deletion with:
     *         - status: HTTP status code
     *         - message: Confirmation message
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException If user lacks delete permission
     */
    public function destroyRegistration(EventRegistration $eventRegistration)
    {
        // Check if user has permission to delete this registration
        Gate::authorize('delete', $eventRegistration);
        $eventRegistration->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Registration deleted successfully.',
        ]);
    }

    /**
     * Delete a club event registration
     *
     * Convenience method for deleting club-specific registrations.
     * Delegates to the general registration deletion method.
     *
     * @param EventRegistration $eventRegistration The club registration to delete
     *
     * @return \Illuminate\Http\JsonResponse JSON response confirming deletion
     *
     * @see destroyRegistration()
     */
    public function destroyClubRegistration(EventRegistration $eventRegistration)
    {
        return $this->destroyRegistration($eventRegistration);
    }

    /**
     * Delete a member event registration
     *
     * Convenience method for deleting member-specific registrations.
     * Delegates to the general registration deletion method.
     *
     * @param EventRegistration $eventRegistration The member registration to delete
     *
     * @return \Illuminate\Http\JsonResponse JSON response confirming deletion
     *
     * @see destroyRegistration()
     */
    public function destroyMemberRegistration(EventRegistration $eventRegistration)
    {
        return $this->destroyRegistration($eventRegistration);
    }

    /**
     * Determine if a user is eligible for a specific event type
     *
     * This method implements the business logic for determining user eligibility
     * based on event type and user status/membership.
     *
     * Eligibility Rules:
     * - Club events: User must be approved AND a club member, event type must be 'club'
     * - Member events: User must be approved AND a general member, event type must be 'members'
     * - Event type must match the expected registration type
     *
     * @param User $user The user to check eligibility for
     * @param Event $event The event to validate against
     * @param string $expectedType The expected registration type ('club' or 'members')
     *
     * @return bool True if user is eligible, false otherwise
     *
     * @see User::isApproved()
     * @see User::isClubMember()
     * @see User::isMember()
     */
    private function isEligible(User $user, Event $event, string $expectedType): bool
    {
        return match ($expectedType) {
            // Club events require user to be approved and a club member
            'club'    => $event->type === 'club' && $user->isApproved() && $user->isClubMember(),
            // Member events require user to be approved and a general member
            'members' => $event->type === 'members' && $user->isApproved() && $user->isMember(),
            default   => false,
        };
    }

    /**
     * Validate registration constraints before creating a new registration
     *
     * This method enforces business rules that must be satisfied before
     * a new registration can be created. All validations must pass for
     * registration to proceed.
     *
     * Validation Rules:
     * 1. No duplicate registrations (one per user per event)
     * 2. Registration deadline must not have passed
     * 3. Event capacity must not be exceeded
     *
     * @param User $user The user attempting to register
     * @param Event $event The event being registered for
     *
     * @return void
     *
     * @throws Exception
     *         - "You already registered for this event." if duplicate registration
     *         - "Deadline reached." if registration deadline has passed
     *         - "Max registration limit reached." if event is at capacity
     */
    private function validateRegistration(User $user, Event $event): void
    {
        // Check if user is already registered for this event
        if (EventRegistration::where('user_id', $user->id)->where('event_id', $event->id)->exists()) {
            throw new Exception('You already registered for this event.');
        }

        // Check if registration deadline has passed
        if (now()->greaterThan($event->registration_deadline)) {
            throw new Exception('Deadline reached.');
        }

        // Check if event has reached maximum capacity
        if (EventRegistration::where('event_id', $event->id)->count() >= $event->max_participants) {
            throw new Exception('Max registration limit reached.');
        }
    }

    /**
     * Create and store a new event registration
     *
     * This method handles the actual database insertion of a new registration.
     * It uses database transactions to ensure data consistency and generates
     * a unique ticket code for the registration.
     *
     * Process:
     * 1. Generate unique ticket code with 'LOLO' prefix
     * 2. Begin database transaction
     * 3. Create registration record with validated data
     * 4. Commit transaction on success or rollback on failure
     *
     * @param User $user The user being registered
     * @param Event $event The event being registered for
     * @param array $validated The validated registration data from the request
     *
     * @return string The generated unique ticket code
     *
     * @throws Exception If database transaction fails or registration creation fails
     *
     * @example
     * Ticket code format: "LOLO 12345678-1234-5678-9012-123456789012"
     */
    private function storeRegistration(User $user, Event $event, array $validated): string
    {
        // Generate unique ticket code with 'LOLO' prefix
        $ticketCode = 'LOLO ' . strtoupper(Str::uuid());

        // Use database transaction to ensure data integrity
        DB::beginTransaction();

        try {
            // Create the registration record
            EventRegistration::create([
                'user_id'             => $user->id,
                'event_id'            => $event->id,
                'ticket_code'         => $ticketCode,
                'registered_at'       => now(),
                'registration_status' => $validated['registration_status'],
                'is_paid'             => $validated['is_paid'],
                'payment_status'      => $validated['payment_status'],
                'payment_reference'   => $validated['payment_reference'],
            ]);

            // Commit the transaction if successful
            DB::commit();
        } catch (Exception $e) {
            // Rollback transaction on failure
            DB::rollBack();
            throw new Exception('Registration failed: ' . $e->getMessage());
        }

        return $ticketCode;
    }
}
