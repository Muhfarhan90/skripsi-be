<?php

use App\Models\AcademicPeriod;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('marks a Midtrans bank transfer notification as paid and activates enrollment', function () {
    Queue::fake();
    config(['services.midtrans.server_key' => 'test-server-key']);

    [$order, $transaction] = createPendingMidtransOrder();
    $payload = validMidtransPayload($transaction->invoice_code, '150000.00', 'test-server-key');

    $this->postJson('/api/payments/midtrans/notification', $payload)
        ->assertOk()
        ->assertJsonPath('success', true);

    $transaction->refresh();
    $order->refresh();

    expect($transaction->status)->toBe('success');
    expect($transaction->external_id)->toBe('midtrans-transaction-001');
    expect($transaction->payment_method)->toBe('bank_transfer');
    expect($transaction->payment_channel)->toBe('bni_va');
    expect($transaction->payment_reference)->toBe('9888888888888888');
    expect($transaction->paid_at)->not()->toBeNull();
    expect($order->status)->toBe('completed');
    expect(Enrollment::query()
        ->where('order_id', $order->id)
        ->where('user_id', $order->user_id)
        ->where('status', 'active')
        ->exists())->toBeTrue();
});

it('sends frontend order detail URL as Midtrans finish callback', function () {
    config([
        'app.frontend_url' => 'http://localhost:3000',
        'services.midtrans.server_key' => 'test-server-key',
    ]);

    Http::fake([
        'app.sandbox.midtrans.com/*' => Http::response([
            'token' => 'snap-token-001',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-001',
        ], 201),
    ]);

    [$order, $transaction] = createPendingMidtransOrder();

    $payload = app(MidtransPaymentService::class)->createSnapTransaction($order, $transaction);

    expect($payload['redirect_url'])->toContain('app.sandbox.midtrans.com');

    Http::assertSent(function (Request $request) use ($order) {
        return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request['callbacks']['finish'] === "http://localhost:3000/student/orders/{$order->id}";
    });
});

it('rejects Midtrans notification with invalid signature', function () {
    config(['services.midtrans.server_key' => 'test-server-key']);

    [$order, $transaction] = createPendingMidtransOrder();
    $payload = validMidtransPayload($transaction->invoice_code, '150000.00', 'test-server-key');
    $payload['signature_key'] = 'invalid-signature';

    $this->postJson('/api/payments/midtrans/notification', $payload)
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect($transaction->fresh()->status)->toBe('pending');
    expect($order->fresh()->status)->toBe('pending');
    expect(Enrollment::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

function createPendingMidtransOrder(): array
{
    Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 1], ['name' => 'admin']));
    $studentRole = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 2], ['name' => 'user']));
    $instructorRole = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 3], ['name' => 'instructor']));

    $student = User::factory()->create(['role_id' => $studentRole->id]);
    $instructor = User::factory()->create(['role_id' => $instructorRole->id]);

    $category = Category::query()->create([
        'name' => 'Programming',
        'slug' => 'programming',
    ]);

    $course = Course::query()->create([
        'category_id' => $category->id,
        'instructor_id' => $instructor->id,
        'title' => 'Laravel Payment Gateway',
        'slug' => 'laravel-payment-gateway',
    ]);

    $period = AcademicPeriod::query()->create([
        'code' => '2026A',
        'name' => '2026 Active Period',
        'start_at' => now()->subDay(),
        'end_at' => now()->addMonth(),
        'enrollment_open_at' => now()->subDay(),
        'enrollment_close_at' => now()->addDay(),
        'is_active' => true,
    ]);

    $offering = CourseOffering::query()->create([
        'course_id' => $course->id,
        'academic_period_id' => $period->id,
        'capacity' => 30,
        'price' => 150000,
        'discount_price' => null,
        'is_active' => true,
    ]);

    $order = Order::query()->create([
        'user_id' => $student->id,
        'order_code' => 'ORD-MIDTRANS-001',
        'subtotal' => 150000,
        'discount' => 0,
        'tax' => 0,
        'admin_fee' => 0,
        'grand_total' => 150000,
        'status' => 'pending',
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'course_offering_id' => $offering->id,
        'price' => 150000,
    ]);

    $transaction = Transaction::query()->create([
        'order_id' => $order->id,
        'invoice_code' => 'INV-MIDTRANS-001',
        'payment_method' => 'midtrans',
        'payment_channel' => 'bni_va',
        'payment_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/sample',
        'amount' => 150000,
        'status' => 'pending',
        'expired_at' => now()->addDay(),
    ]);

    return [$order, $transaction];
}

function validMidtransPayload(string $invoiceCode, string $grossAmount, string $serverKey): array
{
    return [
        'transaction_time' => '2026-06-05 14:30:00',
        'transaction_status' => 'settlement',
        'transaction_id' => 'midtrans-transaction-001',
        'status_message' => 'midtrans payment notification',
        'status_code' => '200',
        'signature_key' => hash('sha512', $invoiceCode . '200' . $grossAmount . $serverKey),
        'settlement_time' => '2026-06-05 14:31:00',
        'payment_type' => 'bank_transfer',
        'order_id' => $invoiceCode,
        'merchant_id' => 'G000000000',
        'gross_amount' => $grossAmount,
        'fraud_status' => 'accept',
        'currency' => 'IDR',
        'va_numbers' => [
            [
                'bank' => 'bni',
                'va_number' => '9888888888888888',
            ],
        ],
    ];
}
