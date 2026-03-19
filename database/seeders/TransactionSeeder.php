<?php

namespace Database\Seeders;

use App\Models\Transaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $transactions = [
            [
                'user_id' => 1,
                'course_id' => 1,
                'invoice_number' => 'INV-20240318-0001',
                'subtotal' => 29.99,
                'grand_total' => 29.99,
                'status' => 'completed',
                'paid_at' => now(),
                'expired_at' => now()->addDays(1),
            ],
            [
                'user_id' => 2,
                'course_id' => 2,
                'invoice_number' => 'INV-20240318-0002',
                'subtotal' => 79.99,
                'grand_total' => 79.99,
                'status' => 'completed',
                'paid_at' => now(),
                'expired_at' => now()->addDays(1),
            ],
            [
                'user_id' => 3,
                'course_id' => 3,
                'invoice_number' => 'INV-20240318-0003',
                'subtotal' => 19.99,
                'grand_total' => 19.99,
                'status' => 'completed',
                'paid_at' => now(),
                'expired_at' => now()->addDays(1),
            ],
        ];

        foreach ($transactions as $transaction) {
            Transaction::create($transaction);
        }
    }
}
