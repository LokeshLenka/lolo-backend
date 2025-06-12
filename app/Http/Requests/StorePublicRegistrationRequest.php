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
            'event_id' => ['required', 'exists:events,id'],
            // 'ticket_code' =>['unique']
            // 'registerd_users
            'is_paid' => ['required', new Enum(IsPaid::class)],
            'payment_status' => ['required', new Enum(PaymentStatus::class)],
            'registration_status' => ['required', new Enum(RegistrationStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [

            'public_user_id.exists' => 'The selected user is invalid.',

            'event_id.exists' => 'The selected event is invalid.',
        ];
    }
}
