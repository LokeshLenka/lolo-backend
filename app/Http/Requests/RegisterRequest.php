<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(User::getRoles())],
            // 'reg_num' => ['required', 'string', 'max:100'],
            // 'first_name' => ['required', 'string', 'max:255'],
            // 'last_name' => ['required', 'string', 'max:255'],
            // 'branch' => ['required', 'string', 'max:4'],
            // 'year' => ['required', 'string', 'max:10'],
            // 'phone_no' => ['required', 'numeric'],
            // 'gender' => ['required', 'string', 'max:6'],
            // 'category_of_interest' => ['string', 'max:1000'],
            // 'experience' => ['string', 'max:1000'],
        ];

        // if ($this->input('registrationtype') === 'management') {
        //     $rules += [
        //         'interest_towards_lolo' => ['required', 'string', 'max:1000'],
        //         'any_club' => ['required', 'string', 'max:1000'],
        //     ];
        // } else if ($this->input('registrationtype') === 'member') {
        //     $rules += [
        //         'instrument_avail' => ['required'],
        //         'other_fileds_of_interset' => ['required', 'string', 'max:1000'],
        //         'passion' => ['required', 'string', 'max:1000'],
        //     ];
        // }

        return $rules;
    }


    public function messages(): array
    {
        return [
            'username.unique' => 'This username is already taken.',
            'email.unique' => 'This email address is already registered.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
            'role.in' => 'Invalid role selected.',
            'reg_num.unique' =>'This registeration number is already taken.',
        ];
    }
}
