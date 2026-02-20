<?php

namespace App\Http\Requests;

use App\Enums\EventType;
use App\Enums\PayableType;
use App\Enums\PayerType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rules\Enum;

class StoreRazorpayRequest extends FormRequest
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
            // Payer Information
            'payer_type' => ['required', new Enum(PayerType::class)],
            'payer_id' => ['required', 'integer', 'min:1'],
            'payer_name' => [
                'required',
                'string',
                'max:255',
                // 'regex:/^[\p{L}\s\-\.]+$/u'
            ],
            'payer_identifier' => ['required', 'string', 'max:255'],

            // Payable Information
            'payable_type' => ['required', new Enum(PayableType::class)],
            'payable_id' => ['required', 'integer', 'min:1'],

            // Razorpay Payment Details (all required for verification)
            'razorpay_order_id' => [
                'required',
                'string',
                'max:255',
                //  'regex:/^order_[A-Za-z0-9]+$/'
            ],
            'razorpay_payment_id' => [
                'required',
                'string',
                'max:255',
                // 'regex:/^pay_[A-Za-z0-9]+$/'
            ],
            'razorpay_signature' => [
                'required',
                'string',
                'size:64',
                // 'regex:/^[a-f0-9]{64}$/'
            ],

            // Payment Amount
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999.99'],

            // Access Token (optional)
            'access_token' => ['nullable', 'string', 'max:64', 'alpha_num'],

            // Payment Method & Status
            'payment_method' => ['required', new Enum(PaymentMethod::class)],
            'status' => ['required', new Enum(PaymentStatus::class)],

            // Gateway Response (optional but validated if present)
            'gateway_response' => ['nullable', 'array'],
            'gateway_response.*' => ['nullable'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Payer Type
            'payer_type.required' => 'The payer type is required.',
            'payer_type.string' => 'The payer type must be a valid string.',
            'payer_type.Illuminate\Validation\Rules\Enum' => 'The selected payer type is invalid.',

            // Payer ID
            'payer_id.required' => 'The payer ID is required.',
            'payer_id.integer' => 'The payer ID must be a valid integer.',
            'payer_id.min' => 'The payer ID must be at least 1.',

            // Payer Name
            'payer_name.required' => 'The payer name is required.',
            'payer_name.string' => 'The payer name must be a valid string.',
            'payer_name.max' => 'The payer name must not exceed 255 characters.',
            'payer_name.regex' => 'The payer name may only contain letters, spaces, hyphens, and periods.',

            // Payer Identifier
            'payer_identifier.required' => 'The payer identifier is required.',
            'payer_identifier.string' => 'The payer identifier must be a valid string.',
            'payer_identifier.max' => 'The payer identifier must not exceed 255 characters.',

            // Payable Type
            'payable_type.required' => 'The payable type is required.',
            'payable_type.string' => 'The payable type must be a valid string.',
            'payable_type.Illuminate\Validation\Rules\Enum' => 'The selected payable type is invalid.',

            // Payable ID
            'payable_id.required' => 'The payable ID is required.',
            'payable_id.integer' => 'The payable ID must be a valid integer.',
            'payable_id.min' => 'The payable ID must be at least 1.',

            // Razorpay Order ID
            'razorpay_order_id.required' => 'The Razorpay order ID is required.',
            'razorpay_order_id.string' => 'The Razorpay order ID must be a valid string.',
            'razorpay_order_id.max' => 'The Razorpay order ID must not exceed 255 characters.',
            'razorpay_order_id.regex' => 'The Razorpay order ID format is invalid. It should start with "order_".',

            // Razorpay Payment ID
            'razorpay_payment_id.required' => 'The Razorpay payment ID is required.',
            'razorpay_payment_id.string' => 'The Razorpay payment ID must be a valid string.',
            'razorpay_payment_id.max' => 'The Razorpay payment ID must not exceed 255 characters.',
            'razorpay_payment_id.regex' => 'The Razorpay payment ID format is invalid. It should start with "pay_".',

            // Razorpay Signature
            'razorpay_signature.required' => 'The Razorpay signature is required for payment verification.',
            'razorpay_signature.string' => 'The Razorpay signature must be a valid string.',
            'razorpay_signature.size' => 'The Razorpay signature must be exactly 64 characters.',
            'razorpay_signature.regex' => 'The Razorpay signature format is invalid. It must be a 64-character hexadecimal string.',

            // Amount
            'amount.required' => 'The payment amount is required.',
            'amount.numeric' => 'The payment amount must be a valid number.',
            'amount.min' => 'The payment amount must be greater than zero.',
            'amount.max' => 'The payment amount exceeds the maximum allowed value.',

            // Access Token
            'access_token.string' => 'The access token must be a valid string.',
            'access_token.max' => 'The access token must not exceed 64 characters.',
            'access_token.alpha_num' => 'The access token must contain only alphanumeric characters.',

            // Payment Method
            'payment_method.required' => 'The payment method is required.',
            'payment_method.string' => 'The payment method must be a valid string.',
            'payment_method.Illuminate\Validation\Rules\Enum' => 'The selected payment method is invalid.',

            // Status
            'status.required' => 'The payment status is required.',
            'status.string' => 'The payment status must be a valid string.',
            'status.Illuminate\Validation\Rules\Enum' => 'The selected payment status is invalid.',

            // Gateway Response
            'gateway_response.array' => 'The gateway response must be a valid array.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payer_type' => 'payer type',
            'payer_id' => 'payer ID',
            'payer_name' => 'payer name',
            'payer_identifier' => 'payer identifier',
            'payable_type' => 'payable type',
            'payable_id' => 'payable ID',
            'razorpay_order_id' => 'Razorpay order ID',
            'razorpay_payment_id' => 'Razorpay payment ID',
            'razorpay_signature' => 'Razorpay signature',
            'amount' => 'payment amount',
            'access_token' => 'access token',
            'payment_method' => 'payment method',
            'status' => 'payment status',
            'gateway_response' => 'gateway response',
        ];
    }

    public function requiresPaymentAuthorization(PayableType $payableType): bool
    {
        // $event = Event::where('uuid', $payableId)->first();
        // if (!$event) {
        //     throw new \Exception("Event not found for payable ID: {$payableId}");
        // }

        // if ($event->type === EventType::Public) {
        //     return true;
        // } else if ($event->type === EventType::ClubMembersOnly) {
        //     return true;
        // } else if ($event->type === EventType::MusicMembersOnly) {
        //     return true;
        // } else {
        //     throw new \Exception("Event with payable ID: {$payableId} has an unsupported event type: {$event->type}");
        // }

        if ($payableType === PayableType::Public) {
            return true; // Public events require authorization
        }

        return false;
    }
}
