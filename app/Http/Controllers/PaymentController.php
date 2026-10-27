<?php

namespace App\Http\Controllers;

use App\Events\PaymentPaid;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Piseth\BakongKhqr\BakongKHQR;
use Piseth\BakongKhqr\Models\IndividualInfo;

class PaymentController extends Controller
{
    /**
     * ================================================================
     * CREATE KHQR PAYMENT
     * ================================================================
     *
     * POST /api/customer/payment/khqr
     */
    public function createKHQR(Request $request)
    {
        // ------------------------------------------------------------
        // 1. Validate request
        // ------------------------------------------------------------

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        try {

            // --------------------------------------------------------
            // 2. Get authenticated customer
            // --------------------------------------------------------

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }


            // --------------------------------------------------------
            // 3. Get order
            // --------------------------------------------------------

            $order = Order::find($request->order_id);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.',
                ], 404);
            }


            // --------------------------------------------------------
            // 4. Check order belongs to authenticated customer
            // --------------------------------------------------------

            if ((int) $order->customer_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot pay for this order.',
                ], 403);
            }


            // --------------------------------------------------------
            // 5. Check if order is already paid
            // --------------------------------------------------------

            $existingPaidPayment = Payment::where('order_id', $order->id)
                ->where('status', 'paid')
                ->first();

            if ($existingPaidPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'This order has already been paid.',
                    'data' => [
                        'payment_id' => $existingPaidPayment->id,
                        'status' => $existingPaidPayment->status,
                    ],
                ], 409);
            }


            // --------------------------------------------------------
            // 6. Get amount from database
            //
            // Never trust amount from Flutter.
            // --------------------------------------------------------

            $amount = (float) $order->total;

            if ($amount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid order amount.',
                ], 422);
            }


            // --------------------------------------------------------
            // 7. Currency
            //
            // 840 = USD
            // --------------------------------------------------------

            $currency = 'USD';
            $currencyCode = 840;


            // --------------------------------------------------------
            // 8. Create unique transaction ID
            // --------------------------------------------------------

            $transactionId =
                'ORDER_' .
                $order->id .
                '_' .
                strtoupper(Str::random(10));


            // --------------------------------------------------------
            // 9. Create Individual KHQR
            // --------------------------------------------------------

            $individualInfo = new IndividualInfo(
                config('services.bakong.account_id'),
                config('services.bakong.merchant_name'),
                config('services.bakong.merchant_city')
            );

            $individualInfo->currency = $currencyCode;
            // A zero amount makes the Bakong library generate a static QR.
            $individualInfo->amount = 0;


            // --------------------------------------------------------
            // 10. Generate static KHQR
            // --------------------------------------------------------

            $result = BakongKHQR::generateIndividual(
                $individualInfo
            );


            // --------------------------------------------------------
            // 11. Get QR data
            // --------------------------------------------------------

            $resultData = is_array($result->data ?? null)
                ? $result->data
                : (array) ($result->data ?? []);

            $qrString = $resultData['qr'] ?? null;
            $md5 = $resultData['md5'] ?? null;


            // --------------------------------------------------------
            // 12. Check result
            // --------------------------------------------------------

            $statusCode = 1;

            if (is_array($result->status ?? null)) {

                $statusCode = (int) (
                    $result->status['code'] ?? 1
                );
            } elseif (is_object($result->status ?? null)) {

                $statusCode = (int) (
                    $result->status->code ?? 1
                );
            }


            if (
                $statusCode !== 0 ||
                empty($qrString) ||
                empty($md5)
            ) {

                return response()->json([
                    'success' => false,
                    'message' => 'Static KHQR generation failed.',
                    'result' => $result,
                ], 500);
            }
            // --------------------------------------------------------
            // 13. QR expiration
            // --------------------------------------------------------

            $expiresAt = now()->addMinutes(30);


            // --------------------------------------------------------
            // 14. Save payment
            // --------------------------------------------------------

            $payment = DB::transaction(function () use (
                $order,
                $user,
                $amount,
                $currency,
                $transactionId,
                $qrString,
                $md5,
                $expiresAt
            ) {

                return Payment::create([

                    'order_id' => $order->id,

                    'user_id' => $user->id,

                    'amount' => $amount,

                    'currency' => $currency,

                    'payment_method' => 'khqr',

                    'transaction_id' => $transactionId,

                    'qr_string' => $qrString,

                    'md5' => $md5,

                    'status' => 'pending',

                    'expires_at' => $expiresAt,

                ]);
            });


            // --------------------------------------------------------
            // 15. Return payment information
            // --------------------------------------------------------

            return response()->json([

                'success' => true,

                'message' => 'KHQR created successfully.',

                'data' => [

                    'payment_id' =>
                    $payment->id,

                    'order_id' =>
                    $payment->order_id,

                    'transaction_id' =>
                    $payment->transaction_id,

                    'amount' =>
                    (float) $payment->amount,

                    'currency' =>
                    $payment->currency,

                    'payment_method' =>
                    $payment->payment_method,

                    'qr' =>
                    $payment->qr_string,

                    'md5' =>
                    $payment->md5,

                    'status' =>
                    $payment->status,

                    'expires_at' =>
                    $payment->expires_at?->toISOString(),

                ],

            ], 201);
        } catch (\Throwable $e) {

            // --------------------------------------------------------
            // 16. Handle exception
            // --------------------------------------------------------

            return response()->json([

                'success' => false,

                'message' =>
                'Failed to create KHQR payment.',

                'error' =>
                $e->getMessage(),

            ], 500);
        }
    }


    /**
     * ================================================================
     * CHECK KHQR PAYMENT
     * ================================================================
     *
     * GET /api/customer/payment/{paymentId}/check
     */
    public function checkPayment(Request $request, ?int $paymentId = null)
    {
        // ------------------------------------------------------------
        // 1. Validate request
        // ------------------------------------------------------------

        if ($paymentId !== null) {
            $request->merge([
                'payment_id' => $paymentId,
            ]);
        }

        $request->validate([
            'payment_id' => 'required|integer|exists:payments,id',
        ]);


        try {

            // --------------------------------------------------------
            // 2. Get authenticated customer
            // --------------------------------------------------------

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }


            // --------------------------------------------------------
            // 3. Get payment
            // --------------------------------------------------------

            $payment = Payment::with('order')
                ->where('id', $request->payment_id)
                ->first();


            if (!$payment) {

                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found.',
                ], 404);
            }


            // --------------------------------------------------------
            // 4. Check payment belongs to user
            // --------------------------------------------------------

            if ((int) $payment->user_id !== (int) $user->id) {

                return response()->json([
                    'success' => false,
                    'message' => 'You cannot check this payment.',
                ], 403);
            }


            // --------------------------------------------------------
            // 5. Already paid
            // --------------------------------------------------------

            if ($payment->status === 'paid') {

                return response()->json([
                    'success' => true,
                    'message' => 'Payment already completed.',
                    'data' => [
                        'payment_id' => $payment->id,
                        'order_id' => $payment->order_id,
                        'status' => 'paid',
                        'amount' => (float) $payment->amount,
                        'currency' => $payment->currency,
                    ],
                ]);
            }


            // --------------------------------------------------------
            // 6. Check expiration
            // --------------------------------------------------------

            if (
                $payment->expires_at &&
                now()->greaterThan($payment->expires_at)
            ) {

                $payment->update([
                    'status' => 'expired',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment QR has expired.',
                    'data' => [
                        'payment_id' => $payment->id,
                        'status' => 'expired',
                    ],
                ], 410);
            }


            // --------------------------------------------------------
            // 7. Get Bakong configuration
            // --------------------------------------------------------

            $apiUrl = rtrim(
                config('services.bakong.api_url'),
                '/'
            );

            $apiToken = config(
                'services.bakong.api_token'
            );


            if (!$apiToken) {

                return response()->json([
                    'success' => false,
                    'message' => 'Bakong API token is not configured.',
                ], 500);
            }


            // --------------------------------------------------------
            // 8. Check transaction by MD5
            // --------------------------------------------------------

            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->post(
                    $apiUrl . '/v1/check_transaction_by_md5',
                    [
                        'md5' => $payment->md5,
                    ]
                );


            // --------------------------------------------------------
            // 9. Handle HTTP error
            // --------------------------------------------------------

            if ($response->failed()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to connect to Bakong API.',
                    'status_code' => $response->status(),
                    'bakong_response' => $response->json(),
                ], 502);
            }


            // --------------------------------------------------------
            // 10. Get Bakong response
            // --------------------------------------------------------

            $bakongData = $response->json();


            $responseCode = (int) (
                $bakongData['responseCode'] ?? -1
            );


            // --------------------------------------------------------
            // 11. Transaction not found / still pending
            // --------------------------------------------------------

            if ($responseCode !== 0) {

                return response()->json([
                    'success' => true,
                    'message' =>
                    'Payment is still pending.',
                    'data' => [
                        'payment_id' =>
                        $payment->id,

                        'order_id' =>
                        $payment->order_id,

                        'status' =>
                        $payment->status,

                        'bakong_response' =>
                        $bakongData,
                    ],
                ]);
            }


            // --------------------------------------------------------
            // 12. Get transaction data
            // --------------------------------------------------------

            $transactionData =
                $bakongData['data'] ?? null;


            if (!$transactionData) {

                return response()->json([
                    'success' => false,
                    'message' =>
                    'Bakong returned an invalid transaction response.',
                    'data' => $bakongData,
                ], 502);
            }


            // --------------------------------------------------------
            // 13. Verify amount
            // --------------------------------------------------------

            $paidAmount = (float) (
                $transactionData['amount'] ?? 0
            );


            $expectedAmount = (float) $payment->amount;


            if (abs($paidAmount - $expectedAmount) > 0.001) {

                return response()->json([
                    'success' => false,
                    'message' =>
                    'Payment amount does not match.',
                    'data' => [
                        'expected_amount' =>
                        $expectedAmount,

                        'paid_amount' =>
                        $paidAmount,
                    ],
                ], 422);
            }


            // --------------------------------------------------------
            // 14. Verify currency
            // --------------------------------------------------------

            $paidCurrency =
                strtoupper(
                    $transactionData['currency'] ?? ''
                );


            if ($paidCurrency !== strtoupper($payment->currency)) {

                return response()->json([
                    'success' => false,
                    'message' =>
                    'Payment currency does not match.',
                    'data' => [
                        'expected_currency' =>
                        $payment->currency,

                        'paid_currency' =>
                        $paidCurrency,
                    ],
                ], 422);
            }


            // --------------------------------------------------------
            // 15. Mark payment as paid
            // --------------------------------------------------------

            DB::transaction(function () use (
                $payment,
                $transactionData
            ) {

                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);


                // Update order status
                if ($payment->order) {

                    $payment->order->update([
                        'status' => 'pending',
                    ]);
                }
            });

            try {
                PaymentPaid::dispatch($payment->fresh());
            } catch (\Throwable $notificationException) {
                Log::error('Payment notification failed after payment was marked paid.', [
                    'payment_id' => $payment->id,
                    'message' => $notificationException->getMessage(),
                ]);
            }


            // --------------------------------------------------------
            // 16. Return success
            // --------------------------------------------------------

            return response()->json([

                'success' => true,

                'message' =>
                'Payment verified successfully.',

                'data' => [

                    'payment_id' =>
                    $payment->id,

                    'order_id' =>
                    $payment->order_id,

                    'status' =>
                    'paid',

                    'amount' =>
                    (float) $payment->amount,

                    'currency' =>
                    $payment->currency,

                    'transaction' =>
                    $transactionData,

                ],

            ]);
        } catch (\Throwable $e) {

            // --------------------------------------------------------
            // Exception
            // --------------------------------------------------------

            Log::error('Payment verification failed.', [
                'payment_id' => $paymentId ?? $request->input('payment_id'),
                'message' => $e->getMessage(),
            ]);

            return response()->json([

                'success' => false,

                'message' =>
                'Failed to check payment.',

                'error' =>
                $e->getMessage(),

            ], 500);
        }
    }
}
