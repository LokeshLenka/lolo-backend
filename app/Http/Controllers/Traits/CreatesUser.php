<?php

namespace App\Http\Controllers\Traits;

use App\Enums\PromotedRole;
use App\Enums\UserApprovalStatus;
use App\Enums\UserRoles;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Str;

trait CreatesUser
{
    protected function createUser(UserRoles $role, array $data): User
    {
        if ($role === UserRoles::ROLE_ADMIN) {
            throw new \Exception("Admin registration is not allowed.");
        }

        $registrationType = Arr::get($data, 'registration_type');

        if (!in_array($registrationType, ['management', 'music'])) {
            throw new \Exception("Invalid registration type.");
        }

        $user = User::create([
            'uuid' => Str::uuid(),
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $role->value,
            'created_by' => $data['created_by'] ?? null,
            'management_level' => 'base',
            'promoted_role' => null,
            'is_approved' => false,
            'is_active' => true,
        ]);

        if (in_array($user->role, User::getRolesWithoutAdmin())) {
            $user->userApproval()->create([
                'uuid' => Str::uuid(),
                'user_id' => $user->id,
                'status' => UserApprovalStatus::PENDING,
                'assigned_ebm_id' => $data['created_by'] ?? null,
                'ebm_assigned_at' => $data['created_by'] ? Carbon::now() : null,
                'ebm_approved_at' => null,
                'assigned_membership_head_id' => null,
                'membership_head_assigned_at' => null,
                'membership_head_approved_at' => null,
            ]);
        }
        return $user;
    }
}
