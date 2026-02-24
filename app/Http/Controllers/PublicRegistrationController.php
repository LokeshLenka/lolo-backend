<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Enums\IsPaid;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Http\Requests\StorePublicRegistrationRequest;
use App\Http\Requests\UpdatePublicRegistrationRequest;
use App\Models\Event;
use App\Models\PublicRegistration;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Str;

class PublicRegistrationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePublicRegistrationRequest $request, string $event_uuid)
    {
        try {

            $validatedData = $request->validated();

            $PublicRegistration = DB::transaction(function () use ($validatedData, $event_uuid) {

                $event = Event::where('uuid', $event_uuid)->firstOrFail();

                if (!$event) {
                    throw new \Exception('Event not found');
                }

                return PublicRegistration::create([
                    'uuid' => (string)Str::uuid(),
                    'public_user_id' => $validatedData['public_user_id'],
                    'reg_num' => $validatedData['reg_num'],
                    'event_id' => $event->id,
                    'ticket_code' => null,
                    'utr' => $validatedData['utr'] ?? null,
                    'is_paid' => IsPaid::NotPaid,
                    'payment_status' => PaymentStatus::PENDING,
                    'registration_status' => RegistrationStatus::PENDING,
                    'updated_by' => null,
                ]);
            });

            Log::info('Public Registration Created', [
                'registration_id' => $PublicRegistration->id,
                'public_user_id' => $PublicRegistration->public_user_id,
                'event_id' => $PublicRegistration->event_id,
            ]);

            return $this->respondSuccess($PublicRegistration, 'Registration successful.');
        } catch (\Throwable $e) {

            Log::error('Public Registration Failed', [
                'error' => $e->getMessage()
            ]);

            return $this->respondError('Registration failed. Please try again later.', 500, $e->getMessage());
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(PublicRegistration $publicRegistration)
    {
        // Pass the instance, not the class. Use the correct ability.
        Gate::authorize('update', $publicRegistration);

        $registration = $publicRegistration->load([
            'publicUser',
            'event:id,uuid,name,description,start_date,end_date,venue,status,fee',
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $registration,
        ], 200);
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePublicRegistrationRequest $request, Event $event, string $publicRegistration)
    {
        Gate::authorize('update', PublicRegistration::class);

        try {
            $validated = $request->validated();

            $updatedRegistration = DB::transaction(function () use ($publicRegistration, $event, $validated) {

                $registration = PublicRegistration::where('uuid', $publicRegistration)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Check requested status (defaults to CONFIRMED if none provided to keep backward compatibility)
                $status = $validated['registration_status'] ?? RegistrationStatus::CONFIRMED->value;

                if ($status === RegistrationStatus::CONFIRMED->value || $status === 'confirmed') {
                    // APPROVAL LOGIC
                    if (empty($registration->ticket_code)) {
                        $ticketCode = Str::upper('LOLO-' . Str::slug($event->name) . '-' . $registration->reg_num);
                    } else {
                        $ticketCode = $registration->ticket_code;
                    }

                    $registration->update([
                        'ticket_code' => $ticketCode,
                        'is_paid' => IsPaid::Paid,
                        'payment_status' => PaymentStatus::SUCCESS,
                        'registration_status' => RegistrationStatus::CONFIRMED,
                        'updated_by' => Auth::id(),
                    ]);
                } else if ($status === RegistrationStatus::CANCELLED->value || $status === 'rejected') {
                    // REJECTION LOGIC
                    $registration->update([
                        'is_paid' => IsPaid::NotPaid,
                        'payment_status' => PaymentStatus::FAILED,
                        'registration_status' => RegistrationStatus::CANCELLED,
                        'updated_by' => Auth::id(),

                    ]);
                }

                return $registration;
            });

            Log::info("Public Registration Status Updated", [
                'registration_id' => $updatedRegistration->id,
                'status' => $updatedRegistration->registration_status,
                'public_user_id' => $updatedRegistration->public_user_id,
                'event_id' => $updatedRegistration->event_id,
                'updated_by' => Auth::id(),
            ]);

            return $this->respondSuccess(
                $updatedRegistration,
                'Registration updated successfully.'
            );
        } catch (\Throwable $e) {
            Log::error('Public Registration Update Failed', [
                'registration_uuid' => $publicRegistration,
                'attempted_by' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return $this->respondError(
                'Registration update failed. Please try again later.',
                500,
                $e->getMessage()
            );
        }
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PublicRegistration $publicRegistration)
    {
        //
    }

    public function showRegistrationsByEvent(Event $event)
    {
        $registrations = PublicRegistration::where('event_id', $event->id)
            ->whereHas('event', function ($q) {
                $q->where('type', EventType::Public->value);
            })
            ->with([
                'publicUser',
                'event:id,uuid,name'
            ])
            ->get();

        if ($registrations->isEmpty()) {
            return response()->json([
                'message' => 'No registrations found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $registrations
        ], 200);
    }
}
