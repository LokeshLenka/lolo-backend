<?php

namespace App\Models;

use App\Enums\AcademicYear;
use App\Enums\BranchType;
use App\Enums\GenderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PublicUser extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'reg_num',
        'name',
        'email',
        'gender',
        'year',
        'branch',
        'phone_no',
        'college_hostel_status',
    ];

    protected $casts = [
        'uuid' => 'string',
        'reg_num' => 'string',
        'name' => 'string',
        'email' => 'string',
        'gender' => GenderType::class,
        'year' => AcademicYear::class,
        'branch' => BranchType::class,
        'phone_no' => 'string',
        'college_hostel_status' => 'boolean',
    ];


    public function publicRegistration(): HasMany
    {
        return $this->hasMany(PublicRegistration::class);
    }
}
