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
        'first_name',
        'last_name',
        'reg_num',
        'branch',
        'year',
        'phone_no',
        'gender',
        'category_of_interest',
        'other_fields_of_interest',
        'experience',
        'instrument_avail',
        'passion',
    ];

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

    public function getRole(): string
    {
        return $this->sub_role;
    }

    public function getRoleEnum(): ?MusicCategories
    {
        return in_array($this->sub_role, MusicCategories::values(), true)
            ? MusicCategories::from($this->sub_role)
            : null;
    }
}
