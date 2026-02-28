<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateEventTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::user()?->isEligibleEventCoordinator() ?? false;
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
            'ticket_code' => ['prohibited']
        ];
    }

    public function messages(): array
    {
        return [
            'public_user_id.prohibited' => 'You are not allowed to modify the ticket owner.',

            'reg_num.prohibited' => 'Registration number cannot be changed after ticket creation.',

            'ticket_code.prohibited' => 'Ticket code cannot be modified.',
        ];
    }
}
