<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class MidtransPaymentService
{
    public function __construct(private readonly TransactionService $transactionService)
    {
    }

    public function createSnapTransaction(Order $order, Transaction $transaction): array
    {
        $serverKey = $this->serverKey();
        $amount = $this->normalizeAmount($transaction->amount);

        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => ['Midtrans payment requires a positive transaction amount.'],
            ]);
        }

        $order->loadMissing(['user', 'items.courseOffering.course']);

        $headers = [];
        $notificationUrl = config('services.midtrans.notification_url');
        if (filled($notificationUrl)) {
            $headers['X-Override-Notification'] = $notificationUrl;
        }

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->withHeaders($headers)
            ->timeout((int) config('services.midtrans.timeout', 15))
            ->post($this->snapBaseUrl() . '/transactions', [
                'transaction_details' => [
                    'order_id' => $transaction->invoice_code,
                    'gross_amount' => $amount,
                ],
                'item_details' => [
                    [
                        'id' => $order->order_code,
                        'price' => $amount,
                        'quantity' => 1,
                        'name' => $this->buildItemName($order),
                    ],
                ],
                'customer_details' => [
                    'first_name' => $order->user?->fullname ?? 'Student',
                    'email' => $order->user?->email,
                ],
                'enabled_payments' => config('services.midtrans.enabled_payments', ['bni_va']),
                'callbacks' => [
                    'finish' => $this->finishRedirectUrl($order),
                ],
                'expiry' => [
                    'unit' => config('services.midtrans.expiry_unit', 'hour'),
                    'duration' => max(1, (int) config('services.midtrans.expiry_duration', 24)),
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Midtrans Snap transaction failed', [
                'invoice_code' => $transaction->invoice_code,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw ValidationException::withMessages([
                'payment' => ['Failed to create Midtrans payment. Please try again.'],
            ]);
        }

        $payload = $response->json();
        if (! is_array($payload) || empty($payload['redirect_url'])) {
            throw ValidationException::withMessages([
                'payment' => ['Midtrans response did not include a payment URL.'],
            ]);
        }

        return $payload;
    }

    public function handleNotification(array $payload): Transaction
    {
        $this->ensureValidSignature($payload);

        $transaction = Transaction::where('invoice_code', $payload['order_id'])
            ->with('order')
            ->firstOrFail();

        $this->ensureAmountMatches($transaction, $payload);

        $status = $this->mapNotificationStatus($payload);
        $updateData = [
            'external_id' => $payload['transaction_id'] ?? $transaction->external_id,
            'payment_method' => $payload['payment_type'] ?? $transaction->payment_method,
            'payment_channel' => $this->resolvePaymentChannel($payload) ?? $transaction->payment_channel,
            'payment_reference' => $this->resolvePaymentReference($payload) ?? $transaction->payment_reference,
            'expired_at' => $this->parseMidtransTime($payload['expiry_time'] ?? null) ?? $transaction->expired_at,
        ];

        if ($status !== null) {
            $updateData['status'] = $status;
        }

        if ($status === 'success') {
            $updateData['paid_at'] = $this->parseMidtransTime(
                $payload['settlement_time'] ?? $payload['transaction_time'] ?? null
            ) ?? now();
        }

        if ($transaction->status === 'success' && $status !== 'success') {
            unset($updateData['status']);
        }

        if (($updateData['status'] ?? null) === 'success' || ($updateData['status'] ?? null) === 'failed') {
            return $this->transactionService->update($transaction->id, $updateData);
        }

        $transaction->update($updateData);

        return $transaction->fresh(['order.user']);
    }

    private function ensureValidSignature(array $payload): void
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (! array_key_exists($field, $payload)) {
                throw ValidationException::withMessages([
                    $field => ["Midtrans notification is missing {$field}."],
                ]);
            }
        }

        $signature = hash(
            'sha512',
            $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $this->serverKey()
        );

        if (! hash_equals($signature, (string) $payload['signature_key'])) {
            throw ValidationException::withMessages([
                'signature_key' => ['Invalid Midtrans notification signature.'],
            ]);
        }
    }

    private function ensureAmountMatches(Transaction $transaction, array $payload): void
    {
        $midtransAmount = (float) ($payload['gross_amount'] ?? 0);
        $localAmount = (float) $transaction->amount;

        if (abs($midtransAmount - $localAmount) >= 1) {
            throw ValidationException::withMessages([
                'gross_amount' => ['Midtrans notification amount does not match local transaction amount.'],
            ]);
        }
    }

    private function mapNotificationStatus(array $payload): ?string
    {
        $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? 'accept'));

        if ($transactionStatus === 'capture') {
            return $fraudStatus === 'accept' ? 'success' : 'pending';
        }

        if ($transactionStatus === 'settlement') {
            return 'success';
        }

        if (in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true)) {
            return 'failed';
        }

        return $transactionStatus === 'pending' ? 'pending' : null;
    }

    private function resolvePaymentChannel(array $payload): ?string
    {
        $paymentType = $payload['payment_type'] ?? null;
        $bank = $payload['va_numbers'][0]['bank'] ?? $payload['bank'] ?? null;

        if ($paymentType === 'bank_transfer' && filled($bank)) {
            return strtolower((string) $bank) . '_va';
        }

        return $paymentType ? strtolower((string) $paymentType) : null;
    }

    private function resolvePaymentReference(array $payload): ?string
    {
        return $payload['va_numbers'][0]['va_number']
            ?? $payload['permata_va_number']
            ?? $payload['bill_key']
            ?? $payload['payment_code']
            ?? null;
    }

    private function parseMidtransTime(?string $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value, 'Asia/Jakarta');
        } catch (Throwable) {
            return null;
        }
    }

    private function buildItemName(Order $order): string
    {
        $courseNames = $order->items
            ->map(fn (OrderItem $item) => $item->courseOffering?->course?->title)
            ->filter()
            ->values();

        $name = $courseNames->isNotEmpty()
            ? $courseNames->implode(', ')
            : "Order {$order->order_code}";

        return mb_substr($name, 0, 50);
    }

    private function normalizeAmount(float|int|string $amount): int
    {
        return (int) round((float) $amount);
    }

    private function finishRedirectUrl(Order $order): string
    {
        $baseUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');

        return "{$baseUrl}/student/orders/{$order->id}";
    }

    private function serverKey(): string
    {
        $serverKey = config('services.midtrans.server_key');
        if (! filled($serverKey)) {
            throw ValidationException::withMessages([
                'payment' => ['MIDTRANS_SERVER_KEY is not configured.'],
            ]);
        }

        return (string) $serverKey;
    }

    private function snapBaseUrl(): string
    {
        return rtrim((string) config('services.midtrans.snap_base_url'), '/');
    }
}
