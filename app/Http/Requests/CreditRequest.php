<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\ValidCreditEligible;
use App\Rules\ValidCreditManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $userId = Auth::id();

        $user = User::find($userId);

        if ($user->canManageCredits()) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id', new ValidCreditEligible],
            'event_id' => ['required', 'exists:events,id'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99.99'],
        ];
    }
}
