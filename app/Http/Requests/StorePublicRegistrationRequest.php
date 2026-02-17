<?php

namespace App\Http\Requests;

use App\Enums\IsPaid;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePublicRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'public_user_id' => ['required', 'exists:public_users,id'],
            'reg_num' => ['required', 'string'],
            // 'is_paid' => ['required', new Enum(IsPaid::class)],
            // 'payment_status' => ['required', new Enum(PaymentStatus::class)],
            // 'registration_status' => ['required', new Enum(RegistrationStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [

            // public_user_id
            'public_user_id.required' => 'Public user is required.',
            'public_user_id.exists' => 'Selected public user does not exist.',

            // reg_num
            'reg_num.required' => 'Registration number is required.',
            'reg_num.string' => 'Registration number must be a valid string.',

            // event_id
            // 'event_id.required' => 'Event is required.',
            // 'event_id.exists' => 'Selected event does not exist.',

            // ticket_code
            // 'ticket_code.string' => 'Ticket code must be a valid string.',
            // 'ticket_code.unique' => 'This ticket code is already in use.',

            // is_paid
            // 'is_paid.required' => 'Payment status is required.',
            // 'is_paid.enum' => 'Invalid payment value selected.',

            // payment_status
            // 'payment_status.required' => 'Payment status is required.',
            // 'payment_status.enum' => 'Invalid payment status selected.',

            // registration_status
            // 'registration_status.required' => 'Registration status is required.',
            // 'registration_status.enum' => 'Invalid registration status selected.',
        ];
    }
}
