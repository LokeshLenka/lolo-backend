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

class UpdateEventRegistration extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'event_id' => ['required', 'exists:events,id'],
            'registered_at' => ['required', 'date'], // changed from 'datetime' to 'date' for Laravel compatibility
            'is_paid' => ['required', new Enum(IsPaid::class)],
            'registration_status' => ['required', new Enum(RegistrationStatus::class)],
            'ticket_code' => ['nullable', 'string'],
            'payment_status' => ['required', new Enum(PaymentStatus::class)],
            'payment_reference' => ['nullable', 'string'],
        ];
    }
}
