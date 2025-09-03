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
        ];

        // Conditional rules based on registration_type
        if ($this->input('registration_type') === 'management') {
            $rules += [

                'management_pofile.first_name' => ['sometimes', 'string', 'max:255'],
                'management_pofile.last_name' => ['sometimes', 'string', 'max:255'],
                'management_pofile.branch' => ['sometimes', new Enum(BranchType::class)],
                'management_pofile.year' => ['sometimes', 'string', 'max:10'],
                'management_pofile.gender' => ['sometimes', 'string', 'max:6'],
                'management_pofile.experience' => ['prohibited'],


                'management_pofile.reg_num' => [
                    'sometimes',
                    'string',
                    'max:10',
                    Rule::unique('management_profiles')->ignore($user->managementProfile?->id)
                ],
                'management_pofile.phone_no' => [
                    'sometimes',
                    'string',
                    'max:15',
                    Rule::unique('management_profiles')->ignore($user->managementProfile?->id)
                ],
                'management_pofile.sub_role' => ['sometimes', new Enum(ManagementCategories::class)],
                'management_pofile.interest_towards_lolo' => ['prohibited'],
                'management_pofile.any_club' => ['prohibited'],
            ];
        } elseif ($this->input('registration_type') === 'music') {
            $rules += [

                'music_pofile.first_name' => ['sometimes', 'string', 'max:255'],
                'music_pofile.last_name' => ['sometimes', 'string', 'max:255'],
                'music_pofile.branch' => ['sometimes', new Enum(BranchType::class)],
                'music_pofile.year' => ['sometimes', 'string', 'max:10'],
                'music_pofile.gender' => ['sometimes', 'string', 'max:6'],
                'music_pofile.experience' => ['prohibited'],


                'music_pofile.reg_num' => [
                    'sometimes',
                    'string',
                    'max:10',
                    Rule::unique('music_profiles')->ignore($user->musicProfile?->id)
                ],
                'music_pofile.phone_no' => [
                    'sometimes',
                    'string',
                    'max:15',
                    Rule::unique('music_profiles')->ignore($user->musicProfile?->id)
                ],
                'music_pofile.sub_role' => ['sometimes', new Enum(MusicCategories::class)],
                'music_pofile.instrument_avail' => ['sometimes'],
                'music_pofile.other_fields_of_interest' => ['prohibited'],
                'music_pofile.passion' => ['prohibited'],
            ];
        } else {
            throw new Exception('Something went wrong.Please check the data.');
        }

        return $rules;
    }
}
