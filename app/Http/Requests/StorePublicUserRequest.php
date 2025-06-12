<?php

namespace App\Http\Requests;

use App\Enums\AcademicYear;
use App\Enums\BranchType;
use App\Enums\GenderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePublicUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reg_num' => ['required', 'string', 'min:10', 'max:10', 'unique:public_users'],
            'name' => ['required', 'string', 'max:10'],
            'gender' => ['required', new Enum(GenderType::class)],
            'year' => ['required', new Enum(AcademicYear::class)],
            'branch' => ['required', new Enum(BranchType::class)],
            'phone_no' => ['nullable', 'string', 'max:15', 'unique:public_users'],
        ];
    }

    public function messages(): array
    {
        return [

            'reg_num.required' => 'Registered number is required.',
            'reg_num.unique' => 'Registered number is already taken.',
            'reg_num.string' => 'Registration number must be a string.',
            'reg_num.min' => 'Registered number should contain atleast minimum 10 characters.',
            'reg_num.max' => 'Registered number should contain atleast maximum 10 characters.',

            'name.required' => 'Name is required.',
            'name.string' => 'Name must be a string.',
            'name.max' => 'Name should take upto maximum 30 characters.',

            'gender.required' => 'Gender is requried.',

            'year.required' => 'Please select the current studying year.',

            'branch.required' => 'Please select valid branch.',

            'phone_no.required' => 'Phone number is required.',
            'phone_no.unique' => 'Phone number is already taken.',
            'phone_no.string' => 'Phone number must be a string.',
            'phone_no.max' => 'Phone number must not exceed 15 digits.',

        ];
    }
}
