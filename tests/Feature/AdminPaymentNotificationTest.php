<?php

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('creates an admin notification when a student submits manual payment proof', function () {
    Queue::fake();

    $adminRole = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 1], ['name' => 'admin']));
    $studentRole = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 2], ['name' => 'user']));
    $instructorRole = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 3], ['name' => 'instructor']));

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'email' => 'admin-notification@example.com',
    ]);

    $instructor = User::factory()->create([
        'role_id' => $instructorRole->id,
        'email' => 'instructor-payment-notification@example.com',
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
    expect(Notification::query()
        ->where('user_id', $instructor->id)
        ->where('type', 'payment.submitted')
        ->exists())->toBeFalse();

    Queue::assertPushed(SendPushNotificationJob::class, 1);
});

it('creates an admin order notification without manual follow-up wording for gateway payments', function () {
    Queue::fake();

    $adminRole = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 1], ['name' => 'admin']));
    $studentRole = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 2], ['name' => 'user']));

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'email' => 'admin-gateway-order@example.com',
    ]);

    $student = User::factory()->create([
        'role_id' => $studentRole->id,
        'fullname' => 'Student Gateway Order',
        'email' => 'student-gateway-order@example.com',
    ]);

    $order = Order::query()->create([
        'user_id' => $student->id,
        'order_code' => 'ORD-GATEWAY-001',
        'subtotal' => 150000,
        'discount' => 0,
        'tax' => 0,
        'admin_fee' => 0,
        'grand_total' => 150000,
        'status' => 'pending',
    ]);

    $transaction = Transaction::query()->create([
        'order_id' => $order->id,
        'invoice_code' => 'INV-GATEWAY-001',
        'payment_method' => 'midtrans',
        'payment_channel' => 'bni_va',
        'payment_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/sample',
        'amount' => 150000,
        'status' => 'pending',
        'expired_at' => now()->addDay(),
    ]);

    app(NotificationService::class)->publishOrderPlaced($order, $transaction);

    $notification = Notification::query()
        ->where('user_id', $admin->id)
        ->where('type', 'order.placed')
        ->first();

    expect($notification)->not()->toBeNull();
    expect($notification->body)->toBe('Student Gateway Order membuat order ORD-GATEWAY-001.');
    expect($notification->body)->not()->toContain('menunggu tindak lanjut admin');
    expect($notification->data)->toMatchArray([
        'order_id' => $order->id,
        'order_code' => 'ORD-GATEWAY-001',
        'transaction_id' => $transaction->id,
        'route' => '/admin/orders/' . $order->id,
    ]);
});
