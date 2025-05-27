<?php

namespace App\Services;

class UserService
{
    //     protected $userRepository;

    //     public function __construct($userRepository)
    //     {
    //         $this->userRepository = $userRepository;
    //     }

    //     public function getUserById($id)
    //     {
    //         $user = $this->userRepository->find($id);

    //         if (!$user) {
    //             throw new \Exception('User not found');
    //         }

    //         return $user;
    //     }
    //     public function updateUser($id, array $data)
    //     {
    //         $user = $this->getUserById($id);

    //         // Validate and sanitize data as needed
    //         $user->fill($data);
    //         $user->save();

    //         return $user;
    //     }
    //     public function deleteUser($id)
    //     {
    //         $user = $this->getUserById($id);

    //         // Perform any necessary cleanup before deletion
    //         $user->delete();

    //         return true;
    //     }
    //     public function createUser(array $data)
    //     {
    //         // Validate and sanitize data as needed
    //         $user = new User($data);
    //         $user->save();

    //         return $user;
    //     }
    //     public function listUsers($filters = [])
    //     {
    //         $query = User::query();

    //         // Apply filters if provided
    //         if (isset($filters['role'])) {
    //             $query->where('role', $filters['role']);
    //         }

    //         if (isset($filters['is_active'])) {
    //             $query->where('is_active', $filters['is_active']);
    //         }

    //         return $query->get();
    //     }
    //     public function getUserByEmail($email)
    //     {
    //         $user = User::where('email', $email)->first();

    //         if (!$user) {
    //             throw new \Exception('User not found');
    //         }

    //         return $user;
    //     }
    //     public function getUserByUsername($username)
    //     {
    //         $user = User::where('username', $username)->first();

    //         if (!$user) {
    //             throw new \Exception('User not found');
    //         }

    //         return $user;
    //     }
    //     public function getUserByPhone($phone)
    //     {
    //         $user = User::where('phone', $phone)->first();

    //         if (!$user) {
    //             throw new \Exception('User not found');
    //         }

    //         return $user;
    //     }
    //     public function getUserByRegistrationNumber($regNum)
    //     {
    //         $user = User::where('reg_num', $regNum)->first();

    //         if (!$user) {
    //             throw new \Exception('User not found');
    //         }

    //         return $user;
    //     }
    //     public function getUserByRole($role)
    //     {
    //         $users = User::where('role', $role)->get();

    //         if ($users->isEmpty()) {
    //             throw new \Exception('No users found with the specified role');
    //         }

    //         return $users;
    //     }
    //     public function getUserByStatus($isActive)
    //     {
    //         $users = User::where('is_active', $isActive)->get();

    //         if ($users->isEmpty()) {
    //             throw new \Exception('No users found with the specified status');
    //         }

    //         return $users;
    //     }
    //     public function getUserByProfile($profileType, $profileId)
    //     {
    //         $user = User::whereHas($profileType, function ($query) use ($profileId) {
    //             $query->where('id', $profileId);
    //         })->first();

    //         if (!$user) {
    //             throw new \Exception('User not found with the specified profile');
    //         }

    //         return $user;
    //     }
    //     public function getUserByApprovalStatus($status)
    //     {
    //         $users = User::whereHas('userApproval', function ($query) use ($status) {
    //             $query->where('status', $status);
    //         })->get();

    //         if ($users->isEmpty()) {
    //             throw new \Exception('No users found with the specified approval status');
    //         }

    //         return $users;
    //     }
    //     public function clearAccountLock($userId)
    //     {
    //         $user = $this->getUserById($userId);

    //         // Only allow if current user is admin
    //         if (!auth()->user() || !$this->isAdmin()) {
    //             throw new \Exception("Unauthorized.");
    //         }

    //         // Clear the last login timestamp
    //         $user->update([
    //             'last_login_at' => null,
    //         ]);
    //     }
    //     public function isAdmin($user)
    //     {
    //         return $user->hasRole(User::ROLE_ADMIN);
    //     }
    //     public function isMembershipCommitteeHead($user)
    //     {
    //         return $user->hasRole(User::ROLE_MCH);
    //     }
    //     public function isExecutiveBodyMember($user)
    //     {
    //         return $user->hasRole(User::ROLE_EBM);
    //     }
    //     public function isCreditManager($user)
    //     {
    //         return $user->hasRole(User::ROLE_CM);
    //     }
    //     public function isEventManager($user)
    //     {
    //         return $user->hasRole(User::ROLE_EO);
    //     }
    //     public function isEventPlanner($user)
    //     {
    //         return $user->hasRole(User::ROLE_EP);
    //     }
    //     public function isMember($user)
    //     {
    //         return $user->hasRole(User::ROLE_MEMBER);
    //     }
    //     public function hasRole($user, string $role): bool
    //     {
    //         return $user->role === $role;
    //     }
    //     public function getAbilitiesByRole(string $role): array
    //     {
    //         return self::ROLE_ABILITIES[$role] ?? [];
    //     }

}
