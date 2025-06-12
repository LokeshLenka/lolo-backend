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
        'reg_num',
        'name',
        'gender',
        'year',
        'branch',
        'phone_no',
    ];

    protected $casts = [
        'reg_num' => 'string',
        'name' => 'string',
        'gender' => GenderType::class,
        'year' => AcademicYear::class,
        'branch' => BranchType::class,
        'phone_no' => 'string',
    ];


    public function publicRegistration(): HasMany
    {
        return $this->hasMany(PublicRegistration::class);
    }
}
