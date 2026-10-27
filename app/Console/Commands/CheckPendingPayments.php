<?php

namespace App\Console\Commands;

use App\Events\PaymentPaid;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CheckPendingPayments extends Command
{
    protected $signature = 'payments:check-pending';

    protected $description = 'Check pending KHQR payments with Bakong';

    public function handle(): int
    {
        $apiUrl = rtrim((string) config('services.bakong.api_url'), '/');
        $apiToken = config('services.bakong.api_token');

        if (!$apiToken) {
            $this->error('Bakong API token is not configured.');
            return self::FAILURE;
        }

        Payment::where('status', 'pending')
            ->where('expires_at', '>', now())
            ->whereNotNull('md5')
            ->each(function (Payment $payment) use ($apiUrl, $apiToken): void {
                $response = Http::withToken($apiToken)
                    ->acceptJson()
                    ->post($apiUrl . '/v1/check_transaction_by_md5', [
                        'md5' => $payment->md5,
                    ]);

                if ($response->failed() || (int) $response->json('responseCode', -1) !== 0) {
                    return;
                }

                $transaction = $response->json('data');
                if (!is_array($transaction)) {
                    return;
                }

                $paidAmount = (float) ($transaction['amount'] ?? 0);
                $paidCurrency = strtoupper((string) ($transaction['currency'] ?? ''));

                if (abs($paidAmount - (float) $payment->amount) > 0.001 ||
                    $paidCurrency !== strtoupper($payment->currency)) {
                    return;
                }

                $paidPayment = DB::transaction(function () use ($payment): ?Payment {
                    $lockedPayment = Payment::whereKey($payment->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$lockedPayment || $lockedPayment->status !== 'pending') {
                        return null;
                    }

                    $lockedPayment->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);

                    $lockedPayment->order?->update(['status' => 'pending']);

                    return $lockedPayment->fresh();
                });

                if ($paidPayment) {
                    PaymentPaid::dispatch($paidPayment);
                }
            });

        return self::SUCCESS;
    }
}
