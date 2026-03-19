<?php

namespace App\Services;

use App\Models\Transaction;

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
        // Generate Unique Invoice Number: INV-20260318-7HEX
        $data['invoice_number'] = $this->generateInvoiceNumber();
        
        // Set default values if not provided
        $data['user_id'] = $data['user_id'] ?? auth()->id();
        $data['status'] = $data['status'] ?? 'pending';
        $data['expired_at'] = now()->addDay(); // Default 24 hours expiry

        return Transaction::create($data);
    }

    private function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $exists = true;
        $invoice = '';

        while ($exists) {
            // Generate 7 characters of random HEX
            $randomHex = strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
            $invoice = "INV-{$date}-{$randomHex}";
            
            // Check if exists to ensure uniqueness
            $exists = Transaction::where('invoice_number', $invoice)->exists();
        }

        return $invoice;
    }

    public function update(int $id, array $data)
    {
        $transaction = $this->findById($id);
        $transaction->update($data);

        return $transaction;
    }

    public function delete(int $id)
    {
        $transaction = $this->findById($id);
        $transaction->delete();

        return true;
    }
}