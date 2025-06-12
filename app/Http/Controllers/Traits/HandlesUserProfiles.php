<?php

namespace App\Http\Controllers\Traits;

use App\Enums\PromotedRole;
use App\Enums\UserRoles;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait HandlesUserProfiles
{
    use CreatesUser;

    protected function createUserWithProfile(UserRoles $role, ?PromotedRole $promotedRole, array $data): User
    {
        return DB::transaction(function () use ($role, $promotedRole, $data) {
            $user = $this->createUser($role, $promotedRole, $data);

            if ($role === UserRoles::ROLE_MUSIC) {

                $this->createMusicProfile($user, $data);
                return $user->load('musicProfile');
            } elseif ($role === UserRoles::ROLE_MANAGEMENT) {

                $this->createManagementProfile($user, $data);
                return $user->load('managementProfile');
            }
        });
    }

    protected function deleteUserWithProfiles(User $user): void
    {
        DB::transaction(function () use ($user) {
            if ($user->managementProfile) {
                $user->managementProfile()->delete();
            } elseif ($user->musicProfile) {
                $user->musicProfile()->delete();
            }

            $user->userApproval()?->delete();
            $user->delete();
        });
    }

    protected function createMusicProfile(User $user, array $data): void
    {
        $user->musicProfile()->create([
            'user_id' => $user->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'reg_num' => $data['reg_num'],
            'branch' => $data['branch'],
            'year' => $data['year'],
            'phone_no' => $data['phone_no'],
            'gender' => $data['gender'],
            'sub_role' => $data['sub_role'],
            'instrument_avail' => $data['instrument_avail'],
            'other_fields_of_interest' => $data['other_fields_of_interest'],
            'experience' => $data['experience'] ?? null,
            'passion' => $data['passion'],
        ]);
    }

    protected function createManagementProfile(User $user, array $data): void
    {
        $user->managementProfile()->create([
            'user_id' => $user->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'reg_num' => $data['reg_num'],
            'branch' => $data['branch'],
            'year' => $data['year'],
            'phone_no' => $data['phone_no'],
            'gender' => $data['gender'],
            'sub_role' => $data['sub_role'],
            'experience' => $data['experience'],
            'interest_towards_lolo' => $data['interest_towards_lolo'],
            'any_club' => $data['any_club'],
        ]);
    }
}
