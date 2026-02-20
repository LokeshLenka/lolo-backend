<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRazorpayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * If your frontend sends "uuid" instead of "public_registration_uuid",
     * this maps it automatically.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('uuid') && !$this->filled('public_registration_uuid')) {
            $this->merge([
                'public_registration_uuid' => $this->input('uuid'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // Your app identifier (single source of truth for payer/payable/amount)
            'public_registration_uuid' => [
                'required',
                'uuid',
                Rule::exists('public_registrations', 'uuid'),
            ],

            // Razorpay success payload (required for signature verification)
            'razorpay_order_id' => [
                'required',
                'string',
                'max:255',
                // optional stricter check:
                // 'regex:/^order_[A-Za-z0-9]+$/'
            ],
            'razorpay_payment_id' => [
                'required',
                'string',
                'max:255',
                // optional stricter check:
                // 'regex:/^pay_[A-Za-z0-9]+$/'
            ],
            'razorpay_signature' => [
                'required',
                'string',
                'max:255', // don't hardcode 64; Razorpay signatures may not always be exactly 64 chars
            ],

            // Optional: if you want to store it, but don't trust it for business logic
            'payment_method' => ['nullable', 'string', 'max:50'],

            // Optional pass-through diagnostics
            'gateway_response' => ['nullable', 'array'],
            'gateway_response.*' => ['nullable'],

            // Optional
            'access_token' => ['required', 'string', 'min:20', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'public_registration_uuid.required' => 'Public registration UUID is required.',
            'public_registration_uuid.uuid' => 'Public registration UUID must be a valid UUID.',
            'public_registration_uuid.exists' => 'Public registration not found.',

            'razorpay_order_id.required' => 'Razorpay order ID is required.',
            'razorpay_payment_id.required' => 'Razorpay payment ID is required.',
            'razorpay_signature.required' => 'Razorpay signature is required to verify payment.',

            'gateway_response.array' => 'Gateway response must be a valid JSON object.',
        ];
    }

    public function attributes(): array
    {
        return [
            'public_registration_uuid' => 'public registration UUID',
            'razorpay_order_id' => 'Razorpay order ID',
            'razorpay_payment_id' => 'Razorpay payment ID',
            'razorpay_signature' => 'Razorpay signature',
            'payment_method' => 'payment method',
            'gateway_response' => 'gateway response',
            'access_token' => 'access token',
        ];
    }
}
