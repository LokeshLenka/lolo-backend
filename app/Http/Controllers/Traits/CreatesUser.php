<?php

namespace App\Http\Controllers\Traits;

use App\Enums\PromotedRole;
use App\Enums\UserRoles;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait CreatesUser
{
    protected function createUser(UserRoles $role, ?PromotedRole $promotedRole, array $data): User
    {
        if ($role === UserRoles::ROLE_ADMIN) {
            throw new \Exception("Admin registration is not allowed.");
        }

        $registrationType = Arr::get($data, 'registration_type');

        if (!in_array($registrationType, ['management', 'music'])) {
            throw new \Exception("Invalid registration type.");
        }

        $user = User::create([
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $role->value,
            'created_by' => $data['created_by'] ?? null,
            'management_level' => $data['management_level'],
            'promoted_role' => $promotedRole->value ?? null,
            'is_approved' => false,
        ]);

        if (in_array($user->role, User::getRolesWithoutAdmin())) {
            $user->userApproval()->create([
                'user_id' => $user->id,
                'status' => 'pending',
            ]);
        }
        return $user;
    }
}
