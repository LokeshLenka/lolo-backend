<?php

namespace App\Http\Requests;

use App\Enums\IsPaid;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePublicRegistrationRequest extends FormRequest
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
            'public_user_id' => ['prohibited'],
            'reg_num' => ['prohibited'],
            'event_id' => ['prohibited'],
            'ticket_code' => ['prohibited'],
            'utr' => ['prohibited'],
            'is_paid' => ['sometimes', new Enum(IsPaid::class)],
            'payment_status' => ['sometimes', new Enum(PaymentStatus::class)],
            'registration_status' => ['sometimes', new Enum(RegistrationStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [

            // Prohibited fields
            'public_user_id.prohibited' => 'Public user cannot be changed.',
            'reg_num.prohibited'        => 'Registration number cannot be modified.',
            'event_id.prohibited'      => 'Event cannot be changed.',
            'ticket_code.prohibited'   => 'Ticket code cannot be edited.',
            'utr.prohibited'   => 'UTR number cannot be edited.',

            // is_paid
            'is_paid.enum' => 'Invalid payment value selected.',

            // payment_status
            'payment_status.enum' => 'Invalid payment status selected.',

            // registration_status
            'registration_status.enum' => 'Invalid registration status selected.',
        ];
    }
}
