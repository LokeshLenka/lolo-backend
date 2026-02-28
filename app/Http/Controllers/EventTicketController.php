<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEventTicketRequest;
use App\Models\EventTicket;
use App\Models\PublicRegistration;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Str;

class EventTicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(EventTicket $eventTicket)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EventTicket $eventTicket)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventTicketRequest $request, string $eventTicket)
    {

        Gate::authorize('update', EventTicket::class);
        try {

            $authID = Auth::id();

            $validated = $request->validated();

            $updatedEventTicket = DB::transaction(function () use ($validated, $eventTicket, $authID) {

                $eventTicket = EventTicket::where('ticket_code', $eventTicket)->lockForUpdate()->firstOrFail();

                $this->verifyTicket($eventTicket, $authID);

                $eventTicket->update([
                    'is_verified' => true,
                    'verified_by' => $authID,
                    'verified_at' => Carbon::now(),
                ]);

                return $eventTicket;
            });

            Log::info("Updated Event Ticket", [
                'ticket_code' => $updatedEventTicket->ticket_code,
                'reg_num' => $updatedEventTicket->reg_num,
                'verified_by' => $authID,
                'verified_at' => $updatedEventTicket->verified_at,
            ]);

            return $this->respondSuccess(
                $updatedEventTicket,
                'Ticket updated successfully.'
            );
        } catch (HttpResponseException $e) {
            Log::error('Ticket Updation Failed', [
                'error' => $e->getMessage()
            ]);

            return $e->getResponse();
        } catch (\Exception $e) {
            Log::error('Ticket Updation Failed', [
                'error' => $e->getMessage()
            ]);

            return $this->respondError('Ticket updation failed. Please validate the details.', 500, $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EventTicket $eventTicket)
    {
        //
    }

    public function verifyTicket(EventTicket $eventTicket, $authID)
    {
        if ($eventTicket->isVerified()) {
            throw new HttpResponseException(
                $this->respondError(
                    'Ticket is already verified',
                    403
                )
            );
        }
    }


    public function copyTicketsFromPublicRegistrationsToEventTickets()
    {
        $created = 0;
        $skipped = 0;
        $failed  = 0;

        try {
            Gate::authorize('copyRecords', EventTicket::class);

            DB::beginTransaction();

            PublicRegistration::chunk(100, function ($registrations) use (&$created, &$skipped, &$failed) {

                foreach ($registrations as $registration) {

                    try {

                        // Guard against null ticket_code
                        if (!$registration->ticket_code) {
                            $failed++;
                            Log::warning('Skipped registration without ticket_code', [
                                'registration_id' => $registration->id,
                            ]);
                            continue;
                        }

                        $exists = EventTicket::where('ticket_code', $registration->ticket_code)->exists();

                        if ($exists) {
                            $skipped++;
                            continue;
                        }

                        EventTicket::create([
                            'uuid' => $registration->uuid ?? Str::uuid(),
                            'public_user_id' => $registration->public_user_id,
                            'event_id' => $registration->event_id,
                            'reg_num' => $registration->reg_num,
                            'ticket_code' => $registration->ticket_code,
                            'is_verified' => false,
                            'verified_by' => null,
                            'verified_at' => null,
                            'created_at' => $registration->created_at,
                            'updated_at' => $registration->updated_at,
                        ]);

                        $created++;
                    } catch (\Throwable $e) {
                        $failed++;

                        Log::error('Failed to copy ticket', [
                            'ticket_code' => $registration->ticket_code,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            DB::commit();

            Log::info('Ticket copy completed', compact('created', 'skipped', 'failed'));

            return $this->respondSuccess(
                compact('created', 'skipped', 'failed'),
                'Ticket copy process completed.'
            );
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Ticket copy failed completely', [
                'error' => $e->getMessage()
            ]);

            return $this->respondError('Ticket copy failed.', 500);
        }
    }
}
