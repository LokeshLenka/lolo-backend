<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\User;

class ValidCreditEligible implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // $value is a single user_id from user_ids array
        if (is_null($value)) return;

        $user = User::find($value);

        if (
            !$user ||
            in_array($user->role, ['admin', 'cm', 'mh']) ||
            !$user->isApproved()
        ) {
            $fail("The user with ID [$value] is not eligible to receive credits.");
        }
    }
}
