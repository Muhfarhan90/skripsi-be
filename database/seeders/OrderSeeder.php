<?php

namespace Database\Seeders;

use App\Models\CourseOffering;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $studentIds = User::query()->pluck('id', 'email');
        $offeringByTitle = CourseOffering::query()->get()->keyBy('title');

        $orders = [
            [
                'order_code' => 'ORD-20260509-ACTIVE',
                'invoice_code' => 'INV-20260509-ACTIVE',
                'student_email' => 'student@example.com',
                'offering_title' => 'Intro Programming - Cohort A1 2026',
                'status' => 'completed',
                'payment_method' => 'Bank Transfer',
                'payment_channel' => 'BCA',
                'transaction_status' => 'success',
                'paid_at' => now()->subDays(5),
                'expired_at' => null,
            ],
            [
                'order_code' => 'ORD-20260509-WAITING',
                'invoice_code' => 'INV-20260509-WAITING',
                'student_email' => 'student.waiting@example.com',
                'offering_title' => 'Advanced Web Dev - Cohort B1 2026',
                'status' => 'completed',
                'payment_method' => 'Virtual Account',
                'payment_channel' => 'Mandiri',
                'transaction_status' => 'success',
                'paid_at' => now()->subDays(2),
                'expired_at' => null,
            ],
            [
                'order_code' => 'ORD-20260509-EXPIRED',
                'invoice_code' => 'INV-20260509-EXPIRED',
                'student_email' => 'student.expired@example.com',
                'offering_title' => 'Health Wellness - Cohort Legacy 2025',
                'status' => 'completed',
                'payment_method' => 'Bank Transfer',
                'payment_channel' => 'BNI',
                'transaction_status' => 'success',
                'paid_at' => now()->subMonths(5),
                'expired_at' => null,
            ],
            [
                'order_code' => 'ORD-20260509-COMPLETE',
                'invoice_code' => 'INV-20260509-COMPLETE',
                'student_email' => 'student.completed@example.com',
                'offering_title' => 'Intro Programming - Cohort Legacy 2025',
                'status' => 'completed',
                'payment_method' => 'Bank Transfer',
                'payment_channel' => 'BRI',
                'transaction_status' => 'success',
                'paid_at' => now()->subMonths(4),
                'expired_at' => null,
            ],
            [
                'order_code' => 'ORD-20260509-PENDING',
                'invoice_code' => 'INV-20260509-PENDING',
                'student_email' => 'student@example.com',
                'offering_title' => 'Advanced Web Dev - Cohort B1 2026',
                'status' => 'pending',
                'payment_method' => 'Virtual Account',
                'payment_channel' => 'Permata',
                'transaction_status' => 'pending',
                'paid_at' => null,
                'expired_at' => now()->addDay(),
            ],
        ];

        foreach ($orders as $seed) {
            $userId = $studentIds->get($seed['student_email']);
            $offering = $offeringByTitle->get($seed['offering_title']);

            if (! $userId || ! $offering) {
                continue;
            }

            $rawPrice = $offering->discount_price ?? $offering->price ?? 0;
            $price = (float) $rawPrice;

            $order = Order::updateOrCreate(
                ['order_code' => $seed['order_code']],
                [
                    'user_id' => $userId,
                    'subtotal' => $price,
                    'discount' => 0,
                    'tax' => 0,
                    'admin_fee' => 0,
                    'grand_total' => $price,
                    'status' => $seed['status'],
                ]
            );

            $order->items()->updateOrCreate(
                ['course_offering_id' => $offering->id],
                [
                    'course_offering_id' => $offering->id,
                    'price' => $price,
                ]
            );

            Transaction::updateOrCreate(
                ['invoice_code' => $seed['invoice_code']],
                [
                    'order_id' => $order->id,
                    'payment_method' => $seed['payment_method'],
                    'payment_channel' => $seed['payment_channel'],
                    'amount' => $price,
                    'status' => $seed['transaction_status'],
                    'paid_at' => $seed['paid_at'],
                    'expired_at' => $seed['expired_at'],
                ]
            );
        }
    }
}
