<?php

namespace App\Http\Requests;

use App\Enums\BranchType;
use App\Enums\ManagementCategories;
use App\Enums\MusicCategories;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

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
            'role' => ['required', Rule::in(User::getRolesWithoutAdmin())],
            'registration_type' => ['required', 'string', 'max:15'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'branch' => ['required', new Enum(BranchType::class)],
            'year' => ['required', 'string', 'max:10'],
            'gender' => ['required', 'string', 'max:6'],
            'experience' => ['string', 'max:1000'],
            'management_level' => ['required', 'string']
        ];

        if ($this->input('registration_type') === 'management') {
            $rules += [
                'reg_num' => ['required', 'string', 'max:10', 'unique:management_profiles'],
                'phone_no' => ['required', 'string', 'max:15', 'unique:management_profiles'],
                'sub_role' => ['required', new Enum(ManagementCategories::class)],

                'interest_towards_lolo' => ['required', 'string', 'max:1000'],
                'any_club' => ['required', 'string', 'max:1000'],

            ];
        } else if ($this->input('registration_type') === 'music') {
            $rules += [
                'reg_num' => ['required', 'string', 'max:10', 'unique:music_profiles'],
                'phone_no' => ['required', 'string', 'max:15', 'unique:music_profiles'],
                'sub_role' => ['required', new Enum(MusicCategories::class)],

                'instrument_avail' => ['required'],
                'other_fields_of_interest' => ['required', 'string', 'max:1000'],
                'passion' => ['required', 'string', 'max:1000'],
            ];
        }

        return $rules;
    }


    public function messages(): array
    {
        return [
            // General user registration fields
            'email.required' => 'Email is required.',
            'email.string' => 'Email must be a valid string.',
            'email.email' => 'Enter a valid email address.',
            'email.max' => 'Email must not exceed 255 characters.',
            'email.unique' => 'This email address is already registered.',

            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a string.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',

            'role.required' => 'Please select a role.',
            'role.in' => 'Invalid role selected.',

            'registration_type.required' => 'Please specify the registration type.',
            'registration_type.string' => 'Registration type must be a string.',
            'registration_type.max' => 'Registration type must not exceed 15 characters.',

            'first_name.required' => 'First name is required.',
            'first_name.string' => 'First name must be a string.',
            'first_name.max' => 'First name must not exceed 255 characters.',

            'last_name.required' => 'Last name is required.',
            'last_name.string' => 'Last name must be a string.',
            'last_name.max' => 'Last name must not exceed 255 characters.',

            'branch.required' => 'Branch is required.',

            'year.required' => 'Academic year is required.',
            'year.string' => 'Year must be a string.',
            'year.max' => 'Year must not exceed 10 characters.',

            'phone_no.required' => 'Phone number is required.',
            'phone_no.string' => 'Phone number must be a string.',
            'phone_no.max' => 'Phone number must not exceed 15 digits.',

            'gender.required' => 'Gender is required.',
            'gender.string' => 'Gender must be a string.',
            'gender.max' => 'Gender must not exceed 6 characters.',

            'sub_role.string' => 'Category of interest must be a string.',
            'sub_role.max' => 'Category of interest must not exceed 1000 characters.',

            'experience.string' => 'Experience must be a string.',
            'experience.max' => 'Experience must not exceed 1000 characters.',

            // Management-specific
            'reg_num.required' => 'Registration number is required.',
            'reg_num.string' => 'Registration number must be a string.',
            'reg_num.max' => 'Registration number must not exceed 10 characters.',
            'reg_num.unique' => 'This registration number is already taken.',

            'interest_towards_lolo.required' => 'Please describe your interest in LoLo.',
            'interest_towards_lolo.string' => 'Interest must be a string.',
            'interest_towards_lolo.max' => 'Interest must not exceed 1000 characters.',

            'any_club.required' => 'Please mention any clubs you are involved in.',
            'any_club.string' => 'Club involvement must be a string.',
            'any_club.max' => 'Club involvement must not exceed 1000 characters.',

            // Member-specific
            'instrument_avail.required' => 'Please specify instrument availability.',

            'other_fields_of_interest.required' => 'Please describe other fields of interest.',
            'other_fields_of_interest.string' => 'Other fields of interest must be a string.',
            'other_fields_of_interest.max' => 'Other fields of interest must not exceed 1000 characters.',

            'passion.required' => 'Please describe your passion.',
            'passion.string' => 'Passion must be a string.',
            'passion.max' => 'Passion must not exceed 1000 characters.',

            'management_level' => 'Management level is required.',
        ];
    }
}
