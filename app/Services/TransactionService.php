<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Order;

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
        
        $data['status'] = $data['status'] ?? 'pending';
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
        $transaction = $this->findById($id);
        $oldStatus = $transaction->status;
        $transaction->update($data);

        // If status changed to success (paid), activate order and enrollments
        if ($oldStatus !== 'success' && $transaction->status === 'success') {
            $order = $transaction->order;
            
            if ($order && $order->status !== 'completed') {
                $order->update(['status' => 'completed']);

                foreach ($order->enrollments as $enrollment) {
                    if ($enrollment->status !== 'active') {
                        $enrollment->update(['status' => 'active']);
                        $enrollment->course->increment('total_students');
                    }
                }
            }

            // Sync paid_at
            if (!$transaction->paid_at) {
                $transaction->update(['paid_at' => now()]);
            }
        }

        return $transaction;
    }

    public function delete(int $id)
    {
        $transaction = $this->findById($id);
        $transaction->delete();

        return true;
    }
}