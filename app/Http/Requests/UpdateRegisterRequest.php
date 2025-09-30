<?php

namespace App\Http\Requests;

use App\Enums\AcademicYear;
use App\Enums\BranchType;
use App\Enums\CollegeHostelStatus;
use App\Enums\GenderType;
use App\Enums\HostelStatus;
use App\Enums\LateralStatus;
use App\Enums\ManagementCategories;
use App\Enums\MusicCategories;
use App\Enums\UserRoles;
use App\Models\User;
use Auth;
use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\ProhibitedIf;
use Log;

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
            'role' => ['sometimes', 'string', Rule::in(User::getRolesWithoutAdmin())],
            'username' => ['prohibited'],
            'promoted_role' => ['prohibited'],
            'management_level' => ['prohibited'],
            'created_by' => ['prohibited'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'branch' => ['sometimes', new Enum(BranchType::class)],
            'year' => ['sometimes', new Enum(AcademicYear::class)],
            'gender' => ['sometimes', new Enum(GenderType::class)],
            'lateral_status' => ['sometimes', new Enum(LateralStatus::class)],
            'hostel_status' => ['sometimes', new Enum(HostelStatus::class)],
            'college_hostel_status' => ['sometimes', new Enum(CollegeHostelStatus::class)],
            'experience' => ['prohibited']
        ];

        // Conditional rules based on registration_type
        if ($this->input('role') === 'management') {
            $rules += [

                'reg_num' => [
                    'sometimes',
                    'string',
                    'max:10',
                    Rule::unique('management_profiles')->ignore($user->managementProfile?->uuid)
                ],
                'phone_no' => [
                    'sometimes',
                    'string',
                    'max:15',
                    Rule::unique('management_profiles')->ignore($user->managementProfile?->uuid)
                ],
                'sub_role' => ['sometimes', new Enum(ManagementCategories::class)],
                'interest_towards_lolo' => ['prohibited'],
                'any_club' => ['prohibited'],
            ];
        } elseif ($this->input('role') === 'music') {
            $rules += [

                'reg_num' => [
                    'sometimes',
                    'string',
                    'max:10',
                    Rule::unique('music_profiles')->ignore($user->musicProfile?->uuid)
                ],
                'phone_no' => [
                    'sometimes',
                    'string',
                    'max:15',
                    Rule::unique('music_profiles')->ignore($user->musicProfile?->uuid)
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
