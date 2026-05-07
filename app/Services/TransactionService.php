<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Order;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function getAll()
    {
        return Transaction::latest()->paginate(10);
    }

    public function findById(int $id)
    {
        return Transaction::findOrFail($id);
    }

    public function create(array $data)
    {
        // Generate Unique Invoice Code
        $data['invoice_code'] = $this->generateInvoiceCode();

        $data['status'] = $this->normalizeStatus($data['status'] ?? 'pending');
        $data['expired_at'] = $data['expired_at'] ?? now()->addDay();

        return Transaction::create($data);
    }

    private function generateInvoiceCode(): string
    {
        $date = now()->format('Ymd');
        $exists = true;
        $invoiceCode = '';

        while ($exists) {
            $randomHex = strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
            $invoiceCode = "INV-{$date}-{$randomHex}";
            $exists = Transaction::where('invoice_code', $invoiceCode)->exists();
        }

        return $invoiceCode;
    }

    public function update(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $transaction = $this->findById($id);
            $oldStatus = $transaction->status;

            if (array_key_exists('status', $data)) {
                $data['status'] = $this->normalizeStatus((string) $data['status']);
            }

            $transaction->update($data);

            // If status changed to success (paid), activate order and enrollments
            if ($oldStatus !== 'success' && $transaction->status === 'success') {
                $order = $transaction->order;
                if ($order) {
                    if ($order->status !== 'completed') {
                        $order->update(['status' => 'completed']);
                    }

                    $order->loadMissing('items');
                    foreach ($order->items as $item) {
                        Enrollment::updateOrCreate(
                            [
                                'user_id' => $order->user_id,
                                'course_id' => $item->course_id,
                            ],
                            [
                                'order_id' => $order->id,
                                'status' => 'active',
                            ]
                        );
                    }
                }

                // Sync paid_at
                if (!$transaction->paid_at) {
                    $transaction->update(['paid_at' => now()]);
                }
            }

            if ($transaction->status === 'failed') {
                $order = $transaction->order;
                if ($order && $order->status !== 'completed') {
                    $order->update(['status' => 'cancelled']);
                }
            }

            return $transaction->fresh();
        });
    }

    public function delete(int $id)
    {
        $transaction = $this->findById($id);
        $transaction->delete();

        return true;
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower($status);

        if ($normalized === 'paid') {
            return 'success';
        }

        if ($normalized === 'refunded') {
            return 'failed';
        }

        return $normalized;
    }
}
