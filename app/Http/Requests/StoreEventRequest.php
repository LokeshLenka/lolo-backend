<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use App\Enums\RegistrationMode;
use App\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use App\Rules\ValidCoordinatorRole;
use App\Rules\ValidEventManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->canCreateEvents();
    }

    public function rules(): array
    {
        return [
            // 'user_id' => ['exists:users,id', new ValidEventManager],
            'coordinator1' => ['nullable', 'different:coordinator2', 'different:coordinator3', 'exists:users,id', new ValidCoordinatorRole],
            'coordinator2' => ['nullable', 'different:coordinator1', 'different:coordinator3', 'exists:users,id', new ValidCoordinatorRole],
            'coordinator3' => ['nullable', 'different:coordinator1', 'different:coordinator2', 'exists:users,id', new ValidCoordinatorRole],
            'name' => ['required', 'string', 'max:255', 'unique:events'],
            'description' => ['required', 'string', 'max:2000'],
            'type' => ['required', new Enum(EventType::class)],
            'start_date' => ['required', 'date', 'after:now'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'venue' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in([EventStatus::UPCOMING])],
            'credits_awarded' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'fee' => ['required', 'numeric'],
            'registration_deadline' => ['required', 'date', 'after:now', 'before:start_date'],
            'max_participants' => ['nullable', 'integer', 'min:0'],
            'registration_mode' => ['required', new Enum(RegistrationMode::class)],
            'registration_place' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            // Coordinators
            'coordinator1.exists' => 'The selected coordinator-1 is invalid.',
            'coordinator2.exists' => 'The selected coordinator-2 is invalid.',
            'coordinator3.exists' => 'The selected coordinator-3 is invalid.',
            'coordinator1.different' => 'Coordinator-1 must be different from the others.',
            'coordinator2.different' => 'Coordinator-2 must be different from the others.',
            'coordinator3.different' => 'Coordinator-3 must be different from the others.',

            // Name
            'name.required' => 'The event name is required.',
            'name.string' => 'The event name must be a valid string.',
            'name.max' => 'The event name may not be greater than 255 characters.',
            'name.unique' => 'This event name is already taken.',

            // Description
            'description.required' => 'Please provide a description for the event.',
            'description.string' => 'The description must be a valid string.',
            'description.max' => 'The description may not exceed 2000 characters.',

            // Type
            'type.required' => 'Please select a valid event type.',

            // Start Date
            'start_date.required' => 'Please provide the start date of the event.',
            'start_date.date' => 'The start date must be a valid date.',
            'start_date.after' => 'The start date must be in the future.',

            // End Date
            'end_date.required' => 'Please provide the end date of the event.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after' => 'The end date must be after the start date.',

            // Venue
            'venue.required' => 'Please provide the venue.',
            'venue.string' => 'The venue must be a valid string.',
            'venue.max' => 'The venue may not be greater than 255 characters.',

            // Status
            'status.required' => 'Please select the event status.',
            'status.in' => 'The status must be set to "upcoming".',

            // Credits
            'credits_awarded.required' => 'Please specify the credits awarded.',
            'credits_awarded.numeric' => 'Credits awarded must be a number.',
            'credits_awarded.min' => 'Credits awarded must be at least 0.',
            'credits_awarded.max' => 'Credits awarded may not exceed 99.99.',

            // Fee
            'fee.required' => 'Please specify the event fee.',
            'fee.numeric' => 'The event fee must be a valid number.',

            // Registration Deadline
            'registration_deadline.required' => 'Please provide a registration deadline.',
            'registration_deadline.date' => 'The registration deadline must be a valid date.',
            'registration_deadline.after' => 'The registration deadline must be in the future.',
            'registration_deadline.before' => 'The registration deadline must be before the event start date.',

            // Max Participants
            'max_participants.integer' => 'Max participants must be an integer.',
            'max_participants.min' => 'Max participants cannot be negative.',

            // Registration Mode
            'registration_mode.required' => 'Please specify the registration mode.',

            // Registration Place
            'registration_place.string' => 'The registration place must be a valid string.',
            'registration_place.max' => 'The registration place may not exceed 150 characters.',
        ];
    }
}
