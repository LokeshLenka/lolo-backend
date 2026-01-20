<?php

namespace App\Models;

use App\Enums\BranchType;
use App\Enums\AcademicYear;
use App\Enums\MusicCategories;
use App\Enums\PromotedRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicProfile extends Model
{
    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'reg_num',
        'branch',
        'year',
        'phone_no',
        'gender',
        'lateral_status',
        'hostel_status',
        'college_hostel_status',
        'category_of_interest',
        'other_fields_of_interest',
        'experience',
        'instrument_avail',
        'passion',
    ];

    protected $hidden = ['id', 'user_id'];

    protected $casts = [
        'branch' => BranchType::class,
        'year' => AcademicYear::class,
        'sub_role' => MusicCategories::class,
        'promoted_role' => PromotedRole::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSubRole(): string
    {
        return $this->sub_role;
    }

    public function getRoleEnum(): ?MusicCategories
    {
        return in_array($this->sub_role, MusicCategories::values(), true)
            ? MusicCategories::from($this->sub_role)
            : null;
    }

    // Scope to filter by musician type
    public function scopeSubRole($query, $type)
    {
        return $query->where('sub_role', $type);
    }

    // Scope to filter by musician branch
    public function scopeBranch($query, $type)
    {
        return $query->where('branch', $type);
    }

    // Scope to filter by musician year
    public function scopeYear($query, $type)
    {
        return $query->where('year', $type);
    }

    // Scope to filter by musician gender
    public function scopeGender($query, $type)
    {
        return $query->where('gender', $type);
    }


    // Scope to filter active/inactive
    // public function scopeActive($query, $isActive = true)
    // {
    //     return $query->where('is_approved', $isActive);
    // }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
