<?php

namespace App\Http\Controllers\Traits;

use App\Enums\UserRoles;
use App\Models\User;
use Gate;
use Illuminate\Support\Facades\DB;
use Str;

trait HandlesUserProfiles
{
    use CreatesUser;

    protected function createUserWithProfile(UserRoles $role, array $data): User
    {
        return DB::transaction(function () use ($role, $data) {

            $user = $this->createUser($role, $data);

            if ($role === UserRoles::ROLE_MUSIC) {
                $this->createMusicProfile($user, $data);
            } elseif ($role === UserRoles::ROLE_MANAGEMENT) {
                $this->createManagementProfile($user, $data);
            } else {
                throw new \InvalidArgumentException('User role does not support profile creation.');
            }

            return $user->load([
                'musicProfile',
                'managementProfile'
            ]);
        });
    }

    protected function deleteUserWithProfiles(User $user): void
    {
        Gate::authorize('canDeleteUser', $user);

        DB::transaction(function () use ($user) {
            // Delete both if ever existed due to legacy or incorrect inserts
            $user->musicProfile()?->delete();
            $user->managementProfile()?->delete();
            $user->userApproval()?->delete();
            $user->delete();
        });
    }

    protected function createMusicProfile(User $user, array $data): void
    {
        // To ensure only one type of profile exists
        if ($user->managementProfile) {
            throw new \LogicException('A user cannot have both Music and Management profiles.');
        }

        $user->musicProfile()->create([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'reg_num' => $data['reg_num'],
            'branch' => $data['branch'],
            'year' => $data['year'],
            'phone_no' => $data['phone_no'],
            'gender' => $data['gender'],
            'lateral_status' => $data['lateral_status'],
            'hostel_status' => $data['hostel_status'],
            'college_hostel_status' => $data['college_hostel_status'],
            'sub_role' => $data['sub_role'],
            'instrument_avail' => $data['instrument_avail'],
            'other_fields_of_interest' => $data['other_fields_of_interest'],
            'experience' => $data['experience'] ?? null,
            'passion' => $data['passion'],
        ]);
    }

    protected function createManagementProfile(User $user, array $data): void
    {
        // To ensure only one type of profile exists
        if ($user->musicProfile) {
            throw new \LogicException('A user cannot have both Music and Management profiles.');
        }

        $user->managementProfile()->create([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'reg_num' => $data['reg_num'],
            'branch' => $data['branch'],
            'year' => $data['year'],
            'phone_no' => $data['phone_no'],
            'gender' => $data['gender'],
            'lateral_status' => $data['lateral_status'],
            'hostel_status' => $data['hostel_status'],
            'college_hostel_status' => $data['college_hostel_status'],
            'sub_role' => $data['sub_role'],
            'experience' => $data['experience'],
            'interest_towards_lolo' => $data['interest_towards_lolo'],
            'any_club' => $data['any_club'],
        ]);
    }
}
