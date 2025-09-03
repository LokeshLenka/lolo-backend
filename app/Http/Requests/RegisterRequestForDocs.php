<?php
// app/Http/Requests/RegisterRequestForDocs.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use App\Enums\AcademicYear;
use App\Enums\BranchType;
use App\Enums\GenderType;
use App\Enums\ManagementCategories;
use App\Enums\MusicCategories;
use App\Enums\UserRoles;

class RegisterRequestForDocs extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'role' => ['required', Rule::in(['music', 'management', 'public'])],
            'registration_type' => ['required', Rule::in(UserRoles::RegistrableRolesWithoutAdmin())],
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'branch' => ['required', new Enum(BranchType::class)],
            'year' => ['required', new Enum(AcademicYear::class)],
            'gender' => ['required', new Enum(GenderType::class)],
            'experience' => ['string'],
            'management_level' => ['required', 'string'],

            // Conditional fields
            'reg_num' => ['string', 'max:10'],
            'phone_no' => ['string', 'max:15'],
            'sub_role' => ['string'],
            'interest_towards_lolo' => ['string'],
            'any_club' => ['string'],
            'instrument_avail' => ['string'],
            'other_fields_of_interest' => ['string'],
            'passion' => ['string'],
        ];
    }
}
