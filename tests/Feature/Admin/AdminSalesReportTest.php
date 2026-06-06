<?php

use App\Models\AcademicPeriod;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function ensureSalesReportRole(string $name): Role
{
    return Role::unguarded(fn () => Role::query()->updateOrCreate(['name' => $name]));
}

function createSalesReportUser(string $roleName, array $attributes = []): User
{
    $role = ensureSalesReportRole($roleName);

    return User::factory()->create(array_merge([
        'role_id' => $role->id,
    ], $attributes));
}

function createSalesReportOffering(User $instructor, string $prefix, array $periodOverrides = []): CourseOffering
{
    $category = Category::create([
        'name' => "{$prefix} Category",
        'slug' => Str::slug("{$prefix}-category"),
        'description' => null,
    ]);

    $course = Course::create([
        'title' => "{$prefix} Course",
        'slug' => Str::slug("{$prefix}-course"),
        'description' => 'Course description',
        'category_id' => $category->id,
        'instructor_id' => $instructor->id,
        'thumbnail' => null,
        'total_duration' => 0,
        'requirements' => null,
        'outcomes' => null,
    ]);

    $periodPrefix = Str::slug($prefix, '-');
    $period = AcademicPeriod::create(array_merge([
        'code' => strtoupper($periodPrefix),
        'name' => "{$prefix} Period",
        'start_at' => '2026-06-01 00:00:00',
        'end_at' => '2026-09-30 23:59:59',
        'enrollment_open_at' => '2026-05-01 00:00:00',
        'enrollment_close_at' => '2026-06-30 23:59:59',
        'is_active' => true,
    ], $periodOverrides));

    return CourseOffering::create([
        'course_id' => $course->id,
        'academic_period_id' => $period->id,
        'capacity' => 40,
        'price' => 500000,
        'discount_price' => null,
        'is_active' => true,
    ]);
}

function createSalesReportOrder(array $attributes): array
{
    /** @var User $student */
    $student = $attributes['student'];
    /** @var Collection<int, array{offering: CourseOffering, price: int|float}> $items */
    $items = collect($attributes['items']);

    $subtotal = (float) ($attributes['subtotal'] ?? $items->sum('price'));
    $discount = (float) ($attributes['discount'] ?? 0);
    $grandTotal = (float) ($attributes['grand_total'] ?? max(0, $subtotal - $discount));
    $orderCreatedAt = $attributes['order_created_at'] ?? '2026-06-10 02:00:00';

    $order = Order::create([
        'user_id' => $student->id,
        'voucher_id' => null,
        'order_code' => $attributes['order_code'] ?? ('ORD-' . strtoupper(Str::random(8))),
        'subtotal' => $subtotal,
        'discount' => $discount,
        'tax' => 0,
        'admin_fee' => 0,
        'note' => $attributes['note'] ?? null,
        'grand_total' => $grandTotal,
        'status' => $attributes['order_status'] ?? 'completed',
    ]);
    $order->forceFill([
        'created_at' => $orderCreatedAt,
        'updated_at' => $orderCreatedAt,
    ])->saveQuietly();

    $items->each(function (array $item) use ($order) {
        OrderItem::create([
            'order_id' => $order->id,
            'course_offering_id' => $item['offering']->id,
            'price' => $item['price'],
        ]);
    });

    $transactionCreatedAt = $attributes['transaction_created_at'] ?? $orderCreatedAt;
    $transaction = Transaction::create([
        'order_id' => $order->id,
        'invoice_code' => $attributes['invoice_code'] ?? ('INV-' . strtoupper(Str::random(8))),
        'external_id' => $attributes['external_id'] ?? null,
        'payment_method' => $attributes['payment_method'] ?? 'manual',
        'payment_channel' => $attributes['payment_channel'] ?? 'bank_transfer',
        'payment_url' => null,
        'payment_reference' => $attributes['payment_reference'] ?? null,
        'payment_proof' => $attributes['payment_proof'] ?? null,
        'amount' => $attributes['amount'] ?? $grandTotal,
        'status' => $attributes['transaction_status'] ?? 'success',
        'paid_at' => $attributes['paid_at'] ?? null,
        'expired_at' => $attributes['expired_at'] ?? '2026-06-20 02:00:00',
        'verified_by' => null,
    ]);
    $transaction->forceFill([
        'created_at' => $transactionCreatedAt,
        'updated_at' => $transactionCreatedAt,
    ])->saveQuietly();

    return [$order, $transaction];
}

afterEach(function () {
    Carbon::setTestNow();
});

it('returns admin sales summary and export with reconciled multi-item revenue allocation', function () {
    Carbon::setTestNow('2026-06-15 10:00:00');

    $admin = createSalesReportUser('admin', ['email' => 'report-admin@example.com']);
    $instructor = createSalesReportUser('instructor', ['email' => 'report-instructor@example.com']);
    $studentA = createSalesReportUser('user', [
        'fullname' => 'Student Alpha',
        'email' => 'student-alpha@example.com',
    ]);
    $studentB = createSalesReportUser('user', [
        'fullname' => 'Student Beta',
        'email' => 'student-beta@example.com',
    ]);

    $alphaOffering = createSalesReportOffering($instructor, 'Alpha', [
        'code' => 'PERIOD-A',
        'name' => 'Period A',
    ]);
    $betaOffering = createSalesReportOffering($instructor, 'Beta', [
        'code' => 'PERIOD-B',
        'name' => 'Period B',
    ]);

    createSalesReportOrder([
        'student' => $studentA,
        'order_code' => 'ORD-SUCCESS-001',
        'items' => [
            ['offering' => $alphaOffering, 'price' => 300000],
            ['offering' => $betaOffering, 'price' => 200000],
        ],
        'subtotal' => 500000,
        'discount' => 50000,
        'grand_total' => 450000,
        'order_status' => 'completed',
        'transaction_status' => 'success',
        'invoice_code' => 'INV-SUCCESS-001',
        'payment_reference' => 'REF-SUCCESS-001',
        'paid_at' => '2026-06-10 03:00:00',
        'transaction_created_at' => '2026-06-10 02:30:00',
    ]);

    createSalesReportOrder([
        'student' => $studentB,
        'order_code' => 'ORD-PENDING-001',
        'items' => [
            ['offering' => $alphaOffering, 'price' => 150000],
        ],
        'subtotal' => 150000,
        'grand_total' => 150000,
        'order_status' => 'pending',
        'transaction_status' => 'pending',
        'invoice_code' => 'INV-PENDING-001',
        'payment_reference' => 'REF-PENDING-001',
        'transaction_created_at' => '2026-06-11 02:00:00',
    ]);

    createSalesReportOrder([
        'student' => $studentB,
        'order_code' => 'ORD-FAILED-001',
        'items' => [
            ['offering' => $betaOffering, 'price' => 100000],
        ],
        'subtotal' => 100000,
        'grand_total' => 100000,
        'order_status' => 'cancelled',
        'transaction_status' => 'failed',
        'invoice_code' => 'INV-FAILED-001',
        'payment_reference' => 'REF-FAILED-001',
        'transaction_created_at' => '2026-06-12 02:00:00',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/admin/reports/sales-summary?from=2026-06-01&to=2026-06-30');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.summary.total_sales', 450000)
        ->assertJsonPath('data.summary.successful_transactions', 1)
        ->assertJsonPath('data.summary.completed_orders', 1)
        ->assertJsonPath('data.summary.unique_buyers', 1);

    $statusBreakdown = collect($response->json('data.status_breakdown'))->keyBy('status');
    expect($statusBreakdown->get('success')['amount'])->toEqual(450000.0);
    expect($statusBreakdown->get('pending')['amount'])->toEqual(150000.0);
    expect($statusBreakdown->get('failed')['amount'])->toEqual(100000.0);
    expect($statusBreakdown->get('success')['count'])->toBe(1);
    expect($statusBreakdown->get('pending')['count'])->toBe(1);
    expect($statusBreakdown->get('failed')['count'])->toBe(1);

    $topCourses = collect($response->json('data.top_courses'));
    expect($topCourses)->toHaveCount(2);
    expect($topCourses->pluck('revenue')->all())->toEqual([270000.0, 180000.0]);
    expect(array_sum($topCourses->pluck('revenue')->all()))->toEqual(450000.0);

    $exportResponse = $this->get('/api/admin/reports/sales/export?from=2026-06-01&to=2026-06-30');

    $exportResponse->assertOk();
    $exportResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($exportResponse->headers->get('content-disposition'))->toContain('admin-sales-report-');

    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $exportResponse->streamedContent());

    expect($csv)->toContain('REF-SUCCESS-001')
        ->toContain('REF-PENDING-001')
        ->toContain('REF-FAILED-001')
        ->toContain('Alpha Course')
        ->toContain('Beta Course')
        ->toContain('30000.00')
        ->toContain('20000.00')
        ->toContain('270000.00')
        ->toContain('180000.00');
});

it('forbids instructors from accessing admin sales report endpoints', function () {
    $instructor = createSalesReportUser('instructor', ['email' => 'instructor-only@example.com']);

    Sanctum::actingAs($instructor);

    $this->getJson('/api/admin/reports/sales-summary')->assertStatus(403);
    $this->get('/api/admin/reports/sales/export')->assertStatus(403);
});

it('applies asia jakarta date boundaries to sales report filters', function () {
    Carbon::setTestNow('2026-06-15 10:00:00');

    $admin = createSalesReportUser('admin', ['email' => 'boundary-admin@example.com']);
    $instructor = createSalesReportUser('instructor', ['email' => 'boundary-instructor@example.com']);
    $student = createSalesReportUser('user', ['email' => 'boundary-student@example.com']);
    $offering = createSalesReportOffering($instructor, 'Boundary');

    createSalesReportOrder([
        'student' => $student,
        'order_code' => 'ORD-BOUNDARY-IN',
        'items' => [
            ['offering' => $offering, 'price' => 120000],
        ],
        'subtotal' => 120000,
        'grand_total' => 120000,
        'order_status' => 'completed',
        'transaction_status' => 'success',
        'invoice_code' => 'INV-BOUNDARY-IN',
        'paid_at' => '2026-05-31 17:30:00',
        'transaction_created_at' => '2026-05-31 17:00:00',
    ]);

    createSalesReportOrder([
        'student' => $student,
        'order_code' => 'ORD-BOUNDARY-OUT',
        'items' => [
            ['offering' => $offering, 'price' => 150000],
        ],
        'subtotal' => 150000,
        'grand_total' => 150000,
        'order_status' => 'completed',
        'transaction_status' => 'success',
        'invoice_code' => 'INV-BOUNDARY-OUT',
        'paid_at' => '2026-06-01 17:30:00',
        'transaction_created_at' => '2026-06-01 17:00:00',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/admin/reports/sales-summary?from=2026-06-01&to=2026-06-01');

    $response->assertOk()
        ->assertJsonPath('data.summary.total_sales', 120000)
        ->assertJsonPath('data.summary.successful_transactions', 1);

    $exportResponse = $this->get('/api/admin/reports/sales/export?from=2026-06-01&to=2026-06-01');
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $exportResponse->streamedContent());

    expect($csv)->toContain('INV-BOUNDARY-IN')
        ->not->toContain('INV-BOUNDARY-OUT');
});

it('filters sales summary by academic period using item-sliced revenue', function () {
    Carbon::setTestNow('2026-06-15 10:00:00');

    $admin = createSalesReportUser('admin', ['email' => 'period-admin@example.com']);
    $instructor = createSalesReportUser('instructor', ['email' => 'period-instructor@example.com']);
    $student = createSalesReportUser('user', ['email' => 'period-student@example.com']);

    $alphaOffering = createSalesReportOffering($instructor, 'PeriodAlpha', [
        'code' => 'PA-2026',
        'name' => 'Period Alpha',
    ]);
    $betaOffering = createSalesReportOffering($instructor, 'PeriodBeta', [
        'code' => 'PB-2026',
        'name' => 'Period Beta',
    ]);

    createSalesReportOrder([
        'student' => $student,
        'order_code' => 'ORD-PERIOD-001',
        'items' => [
            ['offering' => $alphaOffering, 'price' => 300000],
            ['offering' => $betaOffering, 'price' => 200000],
        ],
        'subtotal' => 500000,
        'discount' => 50000,
        'grand_total' => 450000,
        'order_status' => 'completed',
        'transaction_status' => 'success',
        'invoice_code' => 'INV-PERIOD-001',
        'paid_at' => '2026-06-10 03:00:00',
        'transaction_created_at' => '2026-06-10 02:30:00',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/admin/reports/sales-summary?from=2026-06-01&to=2026-06-30&academic_period_id=' . $alphaOffering->academic_period_id);

    $response->assertOk()
        ->assertJsonPath('data.summary.total_sales', 270000)
        ->assertJsonPath('data.summary.successful_transactions', 1)
        ->assertJsonPath('data.top_courses.0.academic_period_id', $alphaOffering->academic_period_id)
        ->assertJsonPath('data.top_courses.0.revenue', 270000);

    expect(collect($response->json('data.top_courses')))->toHaveCount(1);
});
