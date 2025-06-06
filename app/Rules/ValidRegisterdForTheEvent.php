<?php

// namespace App\Rules;

// use Closure;
// use Illuminate\Contracts\Validation\ValidationRule;

// class ValidRegisterdForTheEvent implements ValidationRule
// {
//     /**
//      * Run the validation rule.
//      *
//      * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
//      */
//     public function validate(string $attribute, mixed $value, Closure $fail): void
//     {
//         if (is_null($value)) return;

//         $user = User::find($value);

//         if (!$user || in_array($user->role, ['admin', 'cm', 'mh'])) {
//             $fail("The selected $attribute not eligible to assing cred3its");
//         }
//     }
// }
