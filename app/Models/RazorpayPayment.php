<?php

namespace App\Models;

use App\Enums\PayableType;
use App\Enums\PayerType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class RazorpayPayment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'uuid',
        'payer_type',
        'payer_id',
        'payer_name',
        'payer_identifier',
        'payable_type',
        'payable_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'amount',
        'access_token',
        'payment_method',
        'status',
        'paid_at',
        'gateway_response',
    ];

    protected $casts = [
        'payer_type'     => PayerType::class,
        'payable_type'   => PayableType::class,
        'payment_method' => PaymentMethod::class,
        'status'         => PaymentStatus::class,
        'is_verified'    => 'boolean',
        'paid_at'        => 'datetime',
        'gateway_response' => 'array',
    ];

    /**
     * Since you aren't using Morphing/Models for types,
     * this method manually resolves the target logic.
     */
    public function getPayableDescription(): string
    {
        return $this->payable_type->label() . " (ID: {$this->payable_id})";
    }
}
