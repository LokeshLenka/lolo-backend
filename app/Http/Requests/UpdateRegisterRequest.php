<?php

namespace App\Http\Requests;

use App\Enums\BranchType;
use App\Enums\ManagementCategories;
use App\Enums\MusicCategories;
use App\Enums\UserRoles;
use App\Models\User;
use Auth;
use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Handles validation logic for updating a registered user.
 *
 * All fields are marked as `sometimes` to allow partial updates.
 * Validation adapts based on the registration_type (management/music).
 */
class UpdateRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && (Auth::user()->isAdmin() || Auth::user()->isMembershipHead());
    }

    public function rules(): array
    {
        $user = $this->route('user');

        $rules = [
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'username' => ['prohibited'],
            'role' => ['sometimes', Rule::in(array_map(fn($role) => $role->value, User::getRolesWithoutAdmin()))],
            'registration_type' => ['sometimes', Rule::in(UserRoles::RegistrableRolesWithoutAdmin())],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'branch' => ['sometimes', new Enum(BranchType::class)],
            'year' => ['sometimes', 'string', 'max:10'],
            'gender' => ['sometimes', 'string', 'max:6'],
            'experience' => ['prohibited'],
            'management_level' => ['sometimes', 'string'],
        ];

        // Conditional rules based on registration_type
        if ($this->input('registration_type') === 'management') {
            $rules += [
                'reg_num' => [
                    'sometimes',
                    'string',
                    'max:10',
                    Rule::unique('management_profiles')->ignore($user->managementProfile?->id)
                ],
                'phone_no' => [
                    'sometimes',
                    'string',
                    'max:15',
                    Rule::unique('management_profiles')->ignore($user->managementProfile?->id)
                ],
                'sub_role' => ['sometimes', new Enum(ManagementCategories::class)],
                'interest_towards_lolo' => ['prohibited'],
                'any_club' => ['prohibited'],
            ];
        } elseif ($this->input('registration_type') === 'music') {
            $rules += [
                'reg_num' => [
                    'sometimes',
                    'string',
                    'max:10',
                    Rule::unique('music_profiles')->ignore($user->musicProfile?->id)
                ],
                'phone_no' => [
                    'sometimes',
                    'string',
                    'max:15',
                    Rule::unique('music_profiles')->ignore($user->musicProfile?->id)
                ],
                'sub_role' => ['sometimes', new Enum(MusicCategories::class)],
                'instrument_avail' => ['sometimes'],
                'other_fields_of_interest' => ['prohibited'],
                'passion' => ['prohibited'],
            ];
        } else {
            throw new Exception('Something went wrong.Please check the data.');
        }

        return $rules;
    }
}
