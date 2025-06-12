<?php

namespace App\Models;

use App\Enums\AcademicYear;
use App\Enums\BranchType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ManagementCategories;
use App\Enums\PromotedRole;

class ManagementProfile extends Model
{

    protected $fillable = [
        'first_name',
        'last_name',
        'reg_num',
        'branch',
        'year',
        'phone_no',
        'gender',
        'sub_role',
        'experience',
        'interest_towards_lolo',
        'any_club',
        'management_level',
        'promoted_role'
    ];

    protected $casts = [
        'branch' => BranchType::class,
        'year' => AcademicYear::class,
        'sub_role' => ManagementCategories::class,
        'promoted_role' => PromotedRole::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRole(): string
    {
        return $this->sub_role;
    }

    public function getRoleEnum(): ?ManagementCategories
    {
        return in_array($this->sub_role, ManagementCategories::values(), true)
            ? ManagementCategories::from($this->sub_role)
            : null;
    }
}
