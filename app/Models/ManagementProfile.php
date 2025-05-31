<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'category_of_interest',
        'experience',
        'interest_towards_lolo',
        'any_club',
    ];

    protected $casts = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
