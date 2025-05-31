<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberProfile extends Model
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

    protected $casts = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
