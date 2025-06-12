<?php

namespace App\Http\Requests;

use App\Enums\IsPaid;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Rules\ValidRegistrationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreEventRegistration extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->EligibleForEventRegistrations();
        // return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'exists:events,id'],
            'registered_at' => ['required', 'date'], // changed from 'datetime' to 'date' for Laravel compatibility
            'is_paid' => ['required', new Enum(IsPaid::class)],
            'registration_status' => ['required', new Enum(RegistrationStatus::class)],
            'ticket_code' => ['nullable', 'string'],
            'payment_status' => ['required', new Enum(PaymentStatus::class)],
            'payment_reference' => ['nullable', 'string'],
        ];
    }

    /**
     * Custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [

            'event_id.required' => 'Event ID is required.',
            'event_id.exists' => 'The selected event does not exist.',

            'registered_at.required' => 'The registration date and time is required.',
            'registered_at.date' => 'The registration time must be a valid date.',

            'is_paid.required' => 'Please specify if the event is paid or not.',
            'is_paid.enum' => 'The selected value for is_paid is invalid.',

            'registration_status.required' => 'Registration status is required.',
            'registration_status.enum' => 'The selected registration status is invalid.',

            'ticket_code.string' => 'The ticket code must be a valid string.',

            'payment_status.required' => 'Payment status is required.',
            'payment_status.enum' => 'The selected payment status is invalid.',

            'payment_reference.string' => 'Payment reference must be a valid string.',
        ];
    }
}
