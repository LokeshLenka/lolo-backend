<?php

namespace App\Http\Controllers\Traits;

use App\Http\Resources\UserResource;
use App\Http\Resources\UserResourceCollection;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

trait GetMyRegistrations
{
    /**
     * Get the registrations done by admin
     */
    public function getAdminRegisteredUsers(): AnonymousResourceCollection
    {
        Gate::authorize('adminOnly', User::class);

        $users = $this->getRegistrations();

        return UserResource::collection($users);
    }

    /**
     * Get the registrations done by ebm
     */
    public function getEBMRegisteredUsers()
    {
        Gate::authorize('EBMOnly', User::class);

        return $this->getRegistrations();
    }

    /**
     * Get the registrations done by an authenticated user
     */
    private function getRegistrations()
    {
        return User::where('created_by', Auth::id())
            ->select('id', 'uuid', 'username', 'role', 'is_approved', 'is_active', 'management_level', 'promoted_role')
            ->with([
                'musicProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role,phone_no',
                'managementProfile:id,user_id,first_name,last_name,reg_num,branch,year,gender,sub_role,phone_no',
                'userApproval:id,user_id,ebm_assigned_at,ebm_approved_at',
                'createdBy:id,uuid,username',
            ])
            ->orderBy('created_at', 'desc')
            ->simplePaginate(20);
    }
}
