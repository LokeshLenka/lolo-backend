<?php

namespace App\Http\Controllers;

use App\Enums\IsPaid;
use App\Enums\PayableType;
use App\Enums\PayerType;
use App\Enums\PaymentStatus;
use App\Http\Requests\StoreRazorpayRequest;
use App\Models\PublicRegistration;
use App\Models\RazorpayPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Throwable;

use function Laravel\Prompts\info;

class RazorpayController extends Controller
{
    private Api $api;

    public function __construct()
    {
        // IMPORTANT: move these to config/services.php + .env in real deployments.
        $this->api = new Api(
            env('RAZORPAY_KEY'),
            env('RAZORPAY_SECRET')
        );
    }

    /**
     * STEP 1: Create Razorpay Order + store local PENDING payment row
     */
    public function createRazorpayOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'public_registration_uuid' => ['required', 'uuid'],
            'currency' => 'nullable|string|in:INR',
        ]);

        DB::beginTransaction();

        try {
            $publicRegistration = PublicRegistration::query()
                ->with(['event', 'publicUser'])
                ->where('uuid', $validated['public_registration_uuid'])
                ->lockForUpdate()
                ->firstOrFail();

            $event = $publicRegistration->event;
            if (!$event) {
                DB::rollBack();
                return response()->json(['error' => 'Event not found for this registration'], 422);
            }

            $publicUser = $publicRegistration->publicUser;
            if (!$publicUser) {
                DB::rollBack();
                return response()->json(['error' => 'Public user not found for this registration'], 422);
            }

            // Amount from Events table (fee) -> paise
            $amountPaise = (int) round(((float) $event->fee) * 100);

            if ($amountPaise < 100) {
                DB::rollBack();
                return response()->json(['error' => 'Amount must be at least ₹1'], 422);
            }

            // If there is already a pending payment for this registration, reuse it by creating a fresh Razorpay order
            // OR you can return the existing order_id if you want (here we create a new one).
            $currency = $validated['currency'] ?? 'INR';

            $orderData = [
                'receipt' => 'PR_' . $publicRegistration->id, // very short, always <= 40
                'amount' => $amountPaise,
                'currency' => $currency,
                'payment_capture' => 1,
                'notes' => [
                    'public_registration_uuid' => (string) $publicRegistration->uuid,
                    'event_uuid' => (string) optional($event)->uuid,
                    // 'public_user_id' => (string) optional($publicRegistration->public_user_id),
                ],
            ];

            $razorpayOrder = $this->api->order->create($orderData);


            $accessTokenRaw  = Str::random(60);                 // return to client
            $accessTokenHash = hash('sha256', $accessTokenRaw); // store in DB

            $payment = RazorpayPayment::create([
                'uuid' => (string) Str::uuid(),

                'payer_type' => PayerType::PUBLIC->value,
                'payer_id' => $publicUser->id,
                'payer_name' => $publicUser->name,
                'payer_identifier' => $publicUser->email ?? $publicUser->phone_no ?? $publicUser->name,

                'payable_type' => PayableType::Public->value,
                'payable_id' => $publicRegistration->id,

                'razorpay_order_id' => $razorpayOrder['id'],
                'razorpay_payment_id' => null,
                'razorpay_signature' => null,

                'access_token' => $accessTokenHash,   // ✅ ONLY THIS

                'amount' => $amountPaise,
                'status' => PaymentStatus::PENDING->value,

                'payment_method' => null,
                'paid_at' => null,
                'gateway_response' => null,
            ]);
            DB::commit();

            return response()->json([
                'order_id' => $razorpayOrder['id'],
                'amount' => $amountPaise,
                'currency' => $currency,
                'key' =>  env('RAZORPAY_KEY'),

                'public_registration_uuid' => (string) $publicRegistration->uuid,
                'payment_uuid' => (string) $payment->uuid,

                'payer_name' => $publicUser->name,
                'event_name' => $event->name,
                'access_token' => $accessTokenRaw,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Razorpay Order Creation Failed', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Could not create payment order'], 500);
        }
    }

    /**
     * STEP 2: Verify signature + update local row to SUCCESS
     */
    public function storePayment(StoreRazorpayRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            // 1) Fetch local payment row (server truth)
            $payment = RazorpayPayment::query()
                ->where('razorpay_order_id', $validated['razorpay_order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // 2) Idempotency
            if ($payment->status === PaymentStatus::SUCCESS->value) {
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already recorded.',
                    'payment_uuid' => $payment->uuid,
                ]);
            }

            // 3) Verify access token (hash stored in DB)
            $tokenHash = hash('sha256', $validated['access_token']);
            if (!hash_equals((string) $payment->access_token, (string) $tokenHash)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }


            // 4) Load registration using payment record (prevents uuid swapping)
            $publicRegistration = PublicRegistration::query()
                ->with(['event', 'publicUser'])
                ->where('id', $payment->payable_id)
                ->firstOrFail();

            // 5) Verify Razorpay signature (mandatory)
            $this->api->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $payment->razorpay_order_id, // server order id [web:26]
                'razorpay_payment_id' => $validated['razorpay_payment_id'],
                'razorpay_signature'  => $validated['razorpay_signature'],
            ]);

            // 6) Optional amount check (use event fee if that's your source of truth)
            $expectedAmountPaise = (int) round(((float) optional($publicRegistration->event)->fee) * 100);
            if ($expectedAmountPaise > 0 && (int) $payment->amount !== $expectedAmountPaise) {
                throw new \Exception('Amount mismatch for this registration/order.');
            }

            // 7) Update payment row to SUCCESS
            $payment->update([
                'razorpay_payment_id' => $validated['razorpay_payment_id'],
                'razorpay_signature'  => $validated['razorpay_signature'],
                'status'              => PaymentStatus::SUCCESS->value,
                'payment_method'      => $validated['payment_method'] ?? null,
                'paid_at'             => now(),
                'gateway_response'    => $validated['gateway_response'] ?? [],
            ]);

            // 8) Mark registration paid
            $publicRegistration->update([
                'is_paid' => IsPaid::Paid->value,
                'payment_status' => PaymentStatus::SUCCESS->value,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment verified and stored successfully.',
                'payment_uuid' => $payment->uuid,
            ]);
        } catch (SignatureVerificationError $e) {
            DB::rollBack();
            Log::warning('Razorpay Signature Mismatch', ['data' => $validated]);
            return response()->json(['success' => false, 'message' => 'Invalid Payment Signature'], 400);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Payment Storage Failed', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Internal Server Error'], 500);
        }
    }
}
