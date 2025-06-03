<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use App\Enums\RegistrationMode;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\TaskType;
use App\Rules\ValidCoordinatorRole;
use App\Rules\ValidEBM;
use App\Rules\ValidEventManager;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Enum;

class EventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */

    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->canManageEvents();
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['exists:user,id',new ValidEventManager],
            'coordinator1' => ['nullable', 'different:coordinator2', 'different:coordinator3', 'exists:users,id', new ValidCoordinatorRole],
            'coordinator2' => ['nullable', 'different:coordinator1', 'different:coordinator3', 'exists:users,id', new ValidCoordinatorRole],
            'coordinator3' => ['nullable', 'different:coordinator1', 'different:coordinator2', 'exists:users,id', new ValidCoordinatorRole],
            'name' => ['required', 'string', 'max:255', 'unique:events'],
            'description' =>  ['required', 'string', 'max:2000'],
            'type' => ['required', new Enum(TaskType::class)],
            'timings' => ['required', 'date', 'after:now'],
            'venue' => ['required', 'string', 'max:255'],
            'status' => ['required', new Enum(EventStatus::class)],
            'credits_awarded' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'registration_deadline' => ['required', 'date', 'after:now'],
            'max_participants' => ['nullable', 'integer', 'min:0'],
            'registration_mode' => ['required', new Enum(RegistrationMode::class)],
            'registration_place' => ['string', 'nullable', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [

            'coordinator1.exists' => 'The selected coordinator-1 is invalid',
            'coordinator2.exists' => 'The selected coordinator-2 is invalid',
            'coordinator3.exists' => 'The selected coordinator-3 is invalid',

            'name.required' => 'The event name is required.',
            'name.string' => 'The event name must be a valid string.',
            'name.max' => 'The event name may not be greater than 255 characters.',
            'name.unique' => 'This event name is already taken.',

            'description.required' => 'Please provide a description for the event.',
            'description.string' => 'The description must be a valid string.',
            'description.max' => 'The description may not exceed 2000 characters.',

            'type.required' => 'Please select a valid event type.',

            'timings.required' => 'Please specify the event date and time.',
            'timings.date' => 'The event timing must be a valid date and time.',
            'timings.after' => 'The event timing must be a future date and time.',

            'venue.required' => 'Please provide the venue.',
            'venue.string' => 'The venue must be a valid string.',
            'venue.max' => 'The venue may not be greater than 255 characters.',

            'status.required' => 'Please select the event status.',

            'credits_awarded.required' => 'Please specify the credits awarded.',
            'credits_awarded.numeric' => 'Credits awarded must be a number.',
            'credits_awarded.min' => 'Credits awarded must be at least 0.',

            'registration_deadline.required' => 'Please provide a registration deadline.',
            'registration_deadline.date' => 'The registration deadline must be a valid date.',
            'registration_deadline.after' => 'The registration deadline must be in the future.',

            'max_participants.integer' => 'Max participants must be an integer.',
            'max_participants.min' => 'Max participants cannot be negative.',

            'registration_mode.required' => 'Please specify the registration mode.',

            'registration_place.string' => 'The registration place must be a valid string.',
            'registration_place.max' => 'The registration place may not exceed 150 characters.',
        ];
    }
}
