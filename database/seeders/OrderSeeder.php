<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Data Order Pertama (Sudah Lunas)
        $order1 = Order::create([
            'user_id' => 1,
            'order_code' => 'ORD-20240330-PAYMENT01',
            'subtotal' => 500000,
            'discount' => 0,
            'tax' => 0,
            'admin_fee' => 0,
            'grand_total' => 500000,
            'status' => 'completed',
        ]);

        $order1->items()->create([
            'course_id' => 1,
            'price' => 500000,
        ]);

        Enrollment::create([
            'user_id' => 1,
            'course_id' => 1,
            'order_id' => $order1->id,
            'status' => 'active',
        ]);

        Transaction::create([
            'order_id' => $order1->id,
            'invoice_code' => 'INV-20240330-SUCCESS01',
            'payment_method' => 'Bank Transfer',
            'payment_channel' => 'BCA',
            'amount' => 500000,
            'status' => 'success',
            'paid_at' => now(),
        ]);

        // 2. Data Order Kedua (Masih Pending / Belum Bayar)
        $order2 = Order::create([
            'user_id' => 1,
            'order_code' => 'ORD-20240330-PENDING02',
            'subtotal' => 750000,
            'discount' => 0,
            'tax' => 0,
            'admin_fee' => 0,
            'grand_total' => 750000,
            'status' => 'pending',
        ]);

        $order2->items()->create([
            'course_id' => 2,
            'price' => 750000,
        ]);

        // Enrollment TIDAK di create di awal jika status pending

        Transaction::create([
            'order_id' => $order2->id,
            'invoice_code' => 'INV-20240330-WAITING02',
            'payment_method' => 'Virtual Account',
            'payment_channel' => 'Mandiri',
            'amount' => 750000,
            'status' => 'pending',
            'expired_at' => now()->addDay(),
        ]);
    }
}
