<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\RegistrationMode;
use App\Models\Event;
use App\Rules\ValidateAssignmentOfCredits;
use App\Rules\ValidCoordinatorRole;
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

        return
            Auth::check() &&
            $event &&
            Auth::user()->isAdmin();
    }

    public function rules(): array
    {
        /** @var Event $event */
        $eventUuid = $this->route('event');

        $event = Event::where('uuid', $eventUuid)->first();

        if (!$event) {
            throw new \Exception("Event not found.");
        }

        // throw new \Exception(($event->start_date));
        // var_dump($event->start_date);

        $hasStarted = Carbon::now()->greaterThanOrEqualTo(($event->start_date));
        $isOngoing = $event->status === EventStatus::ONGOING;
        $isCompleted = $event->status === EventStatus::COMPLETED;

        // $hasStarted = true;
        // $isOngoing = false;
        // $isCompleted = false;


        if ($isCompleted) {
            throw new \Exception("You can't update a completed event.");
        }

        return [

            // Cannot change owner after creation
            'user_id' => ['prohibited'],

            // Coordinators allowed before event starts
            'coordinator1' => !$hasStarted
                ? ['nullable', 'different:coordinator2', 'different:coordinator3', 'exists:users,id', new ValidCoordinatorRole]
                : ['prohibited'],
            'coordinator2' => !$hasStarted
                ? ['nullable', 'different:coordinator1', 'different:coordinator3', 'exists:users,id', new ValidCoordinatorRole]
                : ['prohibited'],
            'coordinator3' => !$hasStarted
                ? ['nullable', 'different:coordinator1', 'different:coordinator2', 'exists:users,id', new ValidCoordinatorRole]
                : ['prohibited'],

            // General info
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('events')->ignore($event->id)],
            'description' => ['sometimes', 'string', 'max:2000'],
            'venue' => ['sometimes', 'string', 'max:255'],

            // Dates
            'start_date' => $hasStarted ? ['prohibited'] : ['sometimes', 'date', 'after:now'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'registration_deadline' => $hasStarted
                ? ['prohibited']
                : ['sometimes', 'date', 'after:now', 'before:start_date'],

            // Type & registration details
            'type' => !$hasStarted
                ? ['sometimes', new Enum(EventType::class), new ValidateAssignmentOfCredits('type')]
                : ['prohibited'],
            'registration_mode' => !$hasStarted
                ? ['sometimes', new Enum(RegistrationMode::class)]
                : ['prohibited'],
            'registration_place' => ['nullable', 'string', 'max:150'],
            'max_participants' => ['nullable', 'integer', 'min:0'],

            // Fees & credits
            'fee' => $hasStarted ? ['prohibited'] : ['sometimes', 'numeric'],
            'credits_awarded' => $isOngoing
                ? ['sometimes', 'numeric', 'min:0', 'max:99.99']
                : ($isCompleted ? ['prohibited'] : ['sometimes', 'numeric', 'min:0', 'max:99.99']),

            // Status update
            'status' => ['sometimes', Rule::in([EventStatus::UPCOMING, EventStatus::ONGOING, EventStatus::COMPLETED])],

            // Image handling
            'images' => ['sometimes', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp,bmp,svg', 'max:10240'], // 10MB each
            'alt_txt' => ['sometimes', 'string', 'max:255'],
            'replace_images' => ['sometimes', 'boolean'],
            'images_to_delete' => ['sometimes', 'array'],
            'images_to_delete.*' => ['string', 'exists:images,uuid'],
            // 'name' => ['string', 'max:255', Rule::unique('events')->ignore($event->id)],

            'qr_code' => $hasStarted ? ['prohibited'] : ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'payment_link' => $hasStarted ? ['prohibited'] : ['nullable', 'max:64', 'url']

        ];
    }

    public function messages(): array
    {
        return [
            // Ownership
            'user_id.prohibited' => 'You cannot change the event owner.',

            // Coordinators
            'coordinator1.prohibited' => 'Cannot change coordinator after event starts.',
            'coordinator2.prohibited' => 'Cannot change coordinator after event starts.',
            'coordinator3.prohibited' => 'Cannot change coordinator after event starts.',

            // Dates & restrictions
            'start_date.prohibited' => 'Start date cannot be changed after the event has started.',
            'registration_deadline.prohibited' => 'Cannot change registration deadline after the event has started.',
            'fee.prohibited' => 'Fee cannot be modified after event starts.',
            'credits_awarded.prohibited' => 'Credits cannot be modified after event completion.',
            'type.prohibited' => 'Event type cannot be changed after the event starts.',
            'registration_mode.prohibited' => 'Registration mode cannot be changed after the event starts.',

            // General validation
            'name.max' => 'Event name cannot exceed 255 characters.',
            'description.max' => 'Description may not exceed 2000 characters.',
            'venue.max' => 'Venue cannot exceed 255 characters.',
            'registration_place.max' => 'Registration place may not exceed 150 characters.',
            'end_date.after' => 'End date must be after the start date.',
            'registration_deadline.after' => 'Registration deadline must be in the future.',
            'registration_deadline.before' => 'Registration deadline must be before the start date.',
            'credits_awarded.max' => 'Credits cannot exceed 99.99.',

            // Image messages
            'images.array' => 'The images field must be an array.',
            'images.max' => 'You may upload up to 5 images only.',
            'images.*.image' => 'Each file must be a valid image.',
            'images.*.mimes' => 'Images must be in JPEG, PNG, JPG, GIF, WEBP, BMP, or SVG format.',
            'images.*.max' => 'Each image may not be larger than 10 MB.',
            'replace_images.boolean' => 'The replace_images field must be true or false.',
            'images_to_delete.array' => 'Images to delete must be an array.',
            'images_to_delete.*.exists' => 'One or more selected images do not exist.',

            // QR Code
            'qr_code.prohibited' => 'The QR code cannot be modified after the event has started.',
            'qr_code.image'      => 'The QR code must be a valid image file.',
            'qr_code.mimes'      => 'The QR code must be a file of type: png, jpg, or jpeg.',
            'qr_code.max'        => 'The QR code may not be greater than 2MB.',

            // Payment Link
            'payment_link.prohibited' => 'The payment link cannot be modified after the event has started.',
            'payment_link.string'     => 'The payment link must be a valid string.',
            'payment_link.max'        => 'The payment link may not be greater than 64 characters.',
            'payment_link.url'        => 'The payment link must be a valid URL.',
        ];
    }
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
