<?php

namespace App\Services;

use App\Models\User;
use App\Http\Controllers\Traits\ApproveUsers;

class MHService
{
    use ApproveUsers;

    public function approveUser(User $user): void
    {
        $this->approve($user, 'managebymh');
    }

    public function rejectUser(User $user): void
    {
        $this->reject($user, 'managebymh');
    }
}
