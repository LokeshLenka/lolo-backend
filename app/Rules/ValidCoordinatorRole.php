<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\User;

class ValidCoordinatorRole implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_null($value)) return;

        $user = User::find($value);

        if (!$user || !$user->isEligibleEventCoordinator() || !$user->isApproved()) {
            $fail("The selected $attribute must be a user with role event planner, event organizer, or ebm.");
        }
    }
}
