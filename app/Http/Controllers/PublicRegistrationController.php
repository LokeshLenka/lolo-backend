<?php

namespace App\Http\Controllers;

use App\Enums\IsPaid;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Http\Requests\StorePublicRegistrationRequest;
use App\Http\Requests\UpdatePublicRegistrationRequest;
use App\Models\PublicRegistration;
use GuzzleHttp\Promise\Is;
use Illuminate\Support\Facades\DB;
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
    public function store(StorePublicRegistrationRequest $request)
    {
        try {

            $validatedData = $request->validated();

            $PublicRegistration = DB::transaction(function () use ($validatedData) {

                return PublicRegistration::create([
                    'public_user_id' => $validatedData['public_user_id'],
                    'reg_num' => $validatedData['reg_num'] ?? null,
                    'event_id' => $validatedData['event_id'],
                    'ticket_code' => Str::uuid(),
                    'is_paid' => IsPaid::NotPaid,
                    'payment_status' => PaymentStatus::PENDING,
                    'registration_status' => RegistrationStatus::PENDING,
                ]);
            });

            Log::info('Public Registration Created', [
                'registration_id' => $PublicRegistration->id,
                'public_user_id' => $PublicRegistration->public_user_id,
                'event_id' => $PublicRegistration->event_id,
            ]);

            return $this->respondSuccess($PublicRegistration, 'Registration successful. Please proceed to payment.');
        } catch (\Throwable $e) {

            Log::error('Public Registration Failed', [
                'error' => $e->getMessage()
            ]);

            return $this->respondError('Registration failed. Please try again later.', 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(PublicRegistration $publicRegistration)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePublicRegistrationRequest $request, PublicRegistration $publicRegistration)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PublicRegistration $publicRegistration)
    {
        //
    }
}
