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
use App\Mail\RegistrationStatusMail; // <-- Added Mailable
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail; // <-- Added Mail Facade
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

                $this->validatePublicRegistration($event, $validatedData['reg_num']);

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

            // 1. Load relationships to get the user's email and name
            $PublicRegistration->load(['publicUser', 'event']);

            // 2. Prepare data for the email template
            $emailData = [
                'name'       => $PublicRegistration->publicUser->name, // Adjust field name if your User model uses 'first_name' etc.
                'event_name' => $PublicRegistration->event->name,
                'reg_number' => $PublicRegistration->reg_num,
                'utr_number' => $PublicRegistration->utr,
            ];

            // 3. Queue the INITIATED email AFTER the transaction commits
            Mail::to($PublicRegistration->publicUser->email)
                ->queue(new RegistrationStatusMail('initiated', $emailData));

            Log::info('Public Registration Created', [
                'registration_id' => $PublicRegistration->id,
                'public_user_id' => $PublicRegistration->public_user_id,
                'event_id' => $PublicRegistration->event_id,
            ]);

            return $this->respondSuccess($PublicRegistration, 'Registration successful.');
        } catch (HttpResponseException $e) {
            return $e->getResponse();
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

                $status = $validated['registration_status'] ?? RegistrationStatus::CONFIRMED->value;

                if ($status === RegistrationStatus::CONFIRMED->value || $status === 'confirmed') {

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
                    $registration->update([
                        'is_paid' => IsPaid::NotPaid,
                        'payment_status' => PaymentStatus::FAILED,
                        'registration_status' => RegistrationStatus::CANCELLED,
                        'updated_by' => Auth::id(),
                    ]);
                }

                return $registration;
            });

            // 1. Load relationships to get the user's email and name
            $updatedRegistration->load(['publicUser', 'event']);

            // 2. Prepare data for the email template
            $emailData = [
                'name'       => $updatedRegistration->publicUser->name,
                'event_name' => $updatedRegistration->event->name,
                'reg_number' => $updatedRegistration->reg_num,
                'utr_number' => $updatedRegistration->utr,
                'ticket_code' => $updatedRegistration->ticket_code, // specifically for success
            ];

            // 3. Queue the corresponding SUCCESS or FAILED email based on status
            if ($updatedRegistration->registration_status === RegistrationStatus::CONFIRMED) {
                Mail::to($updatedRegistration->publicUser->email)
                    ->queue(new RegistrationStatusMail('success', $emailData));
            } else if ($updatedRegistration->registration_status === RegistrationStatus::CANCELLED) {
                Mail::to($updatedRegistration->publicUser->email)
                    ->queue(new RegistrationStatusMail('failed', $emailData));
            }

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

    public function validatePublicRegistration(Event $event, string $regNum)
    {
        // 1. Check if the user already registered
        if (PublicRegistration::where('reg_num', $regNum)->where('event_id', $event->id)->exists()) {
            throw new HttpResponseException(
                $this->respondError('This student registration number is already registered for this event. If you need help, please reach out to the event organizer.', 409)

            );
        }

        // 2. Check if the registration deadline has passed
        if (Carbon::now()->greaterThan($event->registration_deadline)) {
            throw new HttpResponseException(
                $this->respondError(
                    'Registrations for this event are now closed as the deadline has passed.',
                    403
                )
            );
        }

        // 3. Check if maximum participants limit has been reached
        if (PublicRegistration::where('event_id', $event->id)->count() >= $event->max_participants) {
            $this->respondError(
                'Registrations are closed because the maximum participant limit has been reached.',
                403
            );
        }
    }
}
