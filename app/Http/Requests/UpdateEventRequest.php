<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\RegistrationMode;
use App\Models\Event;
use App\Rules\ValidateAssignmentOfCredits;
use App\Rules\ValidCoordinatorRole;
use App\Rules\ValidEventManager;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');
        return Auth::check() && $event && Auth::user()->isAdmin();
    }

    public function rules(): array
    {

        /** @var Event $event */
        $event = $this->route('event');

        $event = Event::find($event);

        $hasEnded = now()->greaterThanOrEqualTo($event->end_date);

        if ($hasEnded) {
            throw new \Exception('You can`t update an ended event.');
        }

        // if(type === )

        return [

            'user_id' => ['prohibited'],

            'coordinator1' => ['nullable', 'different:coordinator2', 'different:coordinator3', 'exists:users,id', new ValidCoordinatorRole],
            'coordinator2' => ['nullable', 'different:coordinator1', 'different:coordinator3', 'exists:users,id', new ValidCoordinatorRole],
            'coordinator3' => ['nullable', 'different:coordinator1', 'different:coordinator2', 'exists:users,id', new ValidCoordinatorRole],
            'name' => ['sometimes', 'string', 'max:255', 'unique:events'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'type' => ['sometimes', new Enum(EventType::class), new ValidateAssignmentOfCredits('type')],
            'start_date' => ['sometimes', 'date', 'after:now'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'venue' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in([EventStatus::UPCOMING])],
            'credits_awarded' => ['sometimes', 'numeric', 'min:0', 'max:99.99',],
            'fee' => ['sometimes', 'numeric'],
            'registration_deadline' => ['sometimes', 'date', 'after:now', 'before:start_date'],
            'max_participants' => ['nullable', 'integer', 'min:0'],
            'registration_mode' => ['sometimes', new Enum(RegistrationMode::class)],
            'registration_place' => ['nullable', 'string', 'max:150'],

        ];
    }

    public function messages(): array
    {
        return [
            'user_id.prohibited' => 'You cannot change the event owner.',
            'start_date.prohibited' => 'Start date cannot be changed after the event has started.',
            'registration_deadline.prohibited' => 'Cannot change deadline after the event has started.',
            'fee.prohibited' => 'Fee cannot be modified after event starts.',
            'credits_awarded.prohibited' => 'Credits cannot be modified after event completion.',
            'coordinator1.prohibited' => 'Cannot change coordinator after event starts.',
            'coordinator2.prohibited' => 'Cannot change coordinator after event starts.',
            'coordinator3.prohibited' => 'Cannot change coordinator after event starts.',
            'type.prohibited' => 'Event type cannot be changed after the event starts.',
            'registration_mode.prohibited' => 'Registration mode cannot be changed after event starts.',

            // General messages
            'name.max' => 'Event name cannot exceed 255 characters.',
            'description.max' => 'Description may not exceed 2000 characters.',
            'venue.max' => 'Venue cannot exceed 255 characters.',
            'registration_place.max' => 'Registration place may not exceed 150 characters.',
            'end_date.after' => 'End date must be after start date.',
            'registration_deadline.after' => 'Deadline must be in the future.',
            'registration_deadline.before' => 'Deadline must be before the start date.',
            'credits_awarded.max' => 'Credits cannot exceed 99.99.',
        ];
    }

    // if event updation is given other than admin, considered rules are

    // /** @var Event $event */
    // $event = $this->route('event');

    // $event = Event::find($event);

    // $hasStarted = now()->greaterThanOrEqualTo($event->start_date);
    // $isOngoing = $event->status === EventStatus::Ongoing;
    // $isCompleted = $event->status === EventStatus::Completed;

    //     // 🚫 Cannot change owner after creation
    //     'user_id' => ['prohibited'],

    //     // 🧑‍💼 Coordinators allowed before event starts
    //     'coordinator1' => !$hasStarted ? ['nullable', 'different:coordinator2', 'different:coordinator3', 'exists:users,id', new ValidCoordinatorRole] : ['prohibited'],
    //     'coordinator2' => !$hasStarted ? ['nullable', 'different:coordinator1', 'different:coordinator3', 'exists:users,id', new ValidCoordinatorRole] : ['prohibited'],
    //     'coordinator3' => !$hasStarted ? ['nullable', 'different:coordinator1', 'different:coordinator2', 'exists:users,id', new ValidCoordinatorRole] : ['prohibited'],

    //     // 📌 General info
    //     'name' => ['sometimes', 'string', 'max:255', Rule::unique('events')->ignore($event->id)],
    //     'description' => ['sometimes', 'string', 'max:2000'],
    //     'venue' => ['sometimes', 'string', 'max:255'],

    //     // 🕒 Dates: protected after start
    //     'start_date' => $hasStarted ? ['prohibited'] : ['sometimes', 'date', 'after:now'],
    //     'end_date' => ['sometimes', 'date', 'after:start_date'],
    //     'registration_deadline' => $hasStarted ? ['prohibited'] : ['sometimes', 'date', 'after:now', 'before:start_date'],

    //     // 🎟 Type and registration details
    //     'type' => !$hasStarted ? ['sometimes', new Enum(EventType::class)] : ['prohibited'],
    //     'registration_mode' => !$hasStarted ? ['sometimes', new Enum(RegistrationMode::class)] : ['prohibited'],
    //     'registration_place' => ['nullable', 'string', 'max:150'],
    //     'max_participants' => ['nullable', 'integer', 'min:0'],

    //     // 💳 Fees and credits: lock after start
    //     'fee' => $hasStarted ? ['prohibited'] : ['sometimes', 'numeric'],
    //     'credits_awarded' => $isCompleted ? ['prohibited'] : ['sometimes', 'numeric', 'min:0', 'max:99.99'],

    //     // 🔄 Status updates allowed (e.g., for approval)
    //     'status' => ['sometimes', Rule::in([EventStatus::Upcoming, EventStatus::Ongoing, EventStatus::Completed])],

}
