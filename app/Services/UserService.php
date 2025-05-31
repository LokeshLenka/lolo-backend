<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\User;
use App\Models\Credit;
use App\Models\EventRegistration;
use App\Models\ManagementProfile;
use App\Models\TeamProfile;
use App\Models\MemberProfile;
use Illuminate\Support\Facades\DB;


class UserService extends AuthService
{

    public function getUserById($id)
    {
        $user = User::find($id);

        if (!$user) {
            throw new \Exception('User not found');
        }

        return $user;
    }

    public function updateUser($id, array $data)
    {
        $user = $this->getUserById($id);

        // Validate and sanitize data as needed
        $user->fill($data);
        $user->save();

        return $user;
    }


public function deleteUser($id)
{
    $user = $this->getUserById($id);

    DB::beginTransaction();

    try {
        // Delete related credits
        Credit::where('user_id', $user->id)->get()->each->delete();

        // Delete related blogs
        Blog::where('user_id', $user->id)->get()->each->delete();

        // Delete profiles if they exist
        if ($teamProfile = TeamProfile::where('user_id', $user->id)->first()) {
            $teamProfile->delete();
        }

        if ($managementProfile = ManagementProfile::where('user_id', $user->id)->first()) {
            $managementProfile->delete();
        }

        if ($memberProfile = MemberProfile::where('user_id', $user->id)->first()) {
            $memberProfile->delete();
        }

        // Delete event registrations
        EventRegistration::where('user_id', $user->id)->get()->each->delete();

        // Delete the user
        $user->delete();

        DB::commit();
        return true;

    } catch (\Exception $e) {
        DB::rollBack();
        throw new \Exception("Failed to delete user and related data: " . $e->getMessage());
    }
}


    public function createUser(array $data)
    {
        // Validate and sanitize data as needed
        $user = $this->register($data);
        $user->save();

        return $user;
    }

    public function listUsers($filters = [])
    {
        $query = User::query();

        // Apply filters if provided
        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->get();
    }

    public function getUserByEmail($email)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new \Exception('User not found');
        }

        return $user;
    }

    public function getUserByUsername($username)
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            throw new \Exception('User not found');
        }

        return $user;
    }

    public function getUserByPhone($phone)
    {
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            throw new \Exception('User not found');
        }

        return $user;
    }

    public function getUserByRegistrationNumber($regNum)
    {
        $user = User::where('reg_num', $regNum)->first();

        if (!$user) {
            throw new \Exception('User not found');
        }

        return $user;
    }

    public function getUserByRole($role)
    {
        $users = User::where('role', $role)->get();

        if ($users->isEmpty()) {
            throw new \Exception('No users found with the specified role');
        }

        return $users;
    }

    public function getUserByStatus($isActive)
    {
        $users = User::where('is_active', $isActive)->get();

        if ($users->isEmpty()) {
            throw new \Exception('No users found with the specified status');
        }

        return $users;
    }

    public function getUserByProfile($profileType, $profileId)
    {
        $user = User::whereHas($profileType, function ($query) use ($profileId) {
            $query->where('id', $profileId);
        })->first();

        if (!$user) {
            throw new \Exception('User not found with the specified profile');
        }

        return $user;
    }

    public function getUserByApprovalStatus($status)
    {
        $users = User::whereHas('userApproval', function ($query) use ($status) {
            $query->where('status', $status);
        })->get();

        if ($users->isEmpty()) {
            throw new \Exception('No users found with the specified approval status');
        }

        return $users;
    }
}
