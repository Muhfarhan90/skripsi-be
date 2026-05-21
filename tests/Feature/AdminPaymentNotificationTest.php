<?php

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('creates an admin notification when a student submits manual payment proof', function () {
    Queue::fake();

    $adminRole = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 1], ['name' => 'admin']));
    $studentRole = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 2], ['name' => 'user']));

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'email' => 'admin-notification@example.com',
    ]);

    $student = User::factory()->create([
        'role_id' => $studentRole->id,
        'email' => 'student-payment@example.com',
    ]);

    $order = Order::query()->create([
        'user_id' => $student->id,
        'order_code' => 'ORD-TEST-001',
        'subtotal' => 150000,
        'discount' => 0,
        'tax' => 0,
        'admin_fee' => 0,
        'grand_total' => 150000,
        'status' => 'pending',
    ]);

    $transaction = Transaction::query()->create([
        'order_id' => $order->id,
        'invoice_code' => 'INV-TEST-001',
        'payment_method' => 'manual',
        'amount' => 150000,
        'status' => 'pending',
        'expired_at' => now()->addDay(),
    ]);

    app(OrderService::class)->submitPaymentByStudent($student->id, $order->id, [
        'payment_reference' => 'TRF-12345',
        'payment_proof' => 'proofs/payment-1.png',
    ]);

    $notification = Notification::query()
        ->where('user_id', $admin->id)
        ->where('type', 'payment.submitted')
        ->first();

    expect($notification)->not()->toBeNull();
    expect($notification->reference_type)->toBe('transaction');
    expect($notification->reference_id)->toBe($transaction->id);
    expect($notification->actor_id)->toBe($student->id);
    expect($notification->data)->toMatchArray([
        'transaction_id' => $transaction->id,
        'order_id' => $order->id,
        'order_code' => 'ORD-TEST-001',
        'student_id' => $student->id,
        'student_name' => $student->fullname,
        'route' => '/admin/orders',
    ]);

    $transaction->refresh();
    expect($transaction->payment_reference)->toBe('TRF-12345');
    expect($transaction->payment_proof)->toBe('proofs/payment-1.png');

    Queue::assertPushed(SendPushNotificationJob::class, 1);
});
