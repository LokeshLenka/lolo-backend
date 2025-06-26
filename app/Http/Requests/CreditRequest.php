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

        if (Auth::user()->canManageCredits()) {
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
            'user_id' => ['sometimes', 'exists:users,id'],
            'user_ids' => ['sometimes', 'exists:users,id'],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:99.99'],
        ];
    }


    public function messages()
    {
        return [
            'user_id.exists' => 'User not found',

            'amount.max' => 'The maximum credits can assign is 99',
            'amount.min' => 'The minimum credits can assign is 0'
        ];
    }
}
