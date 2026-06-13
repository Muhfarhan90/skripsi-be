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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createReportRole(int $id, string $name): Role
{
    return Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => $id], ['name' => $name]));
}

function createReportAdmin(): User
{
    $role = createReportRole(1, 'admin');

    return User::factory()->create([
        'role_id' => $role->id,
        'email' => 'report-admin@example.com',
    ]);
}

function createReportStudent(array $attributes = []): User
{
    $role = createReportRole(2, 'student');

    return User::factory()->create(array_merge([
        'role_id' => $role->id,
    ], $attributes));
}

function createReportInstructor(array $attributes = []): User
{
    $role = createReportRole(3, 'instructor');

    return User::factory()->create(array_merge([
        'role_id' => $role->id,
    ], $attributes));
}

function createReportCourseOffering(User $instructor, string $prefix): CourseOffering
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

    $period = AcademicPeriod::create([
        'code' => strtoupper(Str::slug($prefix, '-')),
        'name' => "{$prefix} Period",
        'start_at' => '2026-01-10 09:00:00',
        'end_at' => '2026-04-10 17:00:00',
        'enrollment_open_at' => '2025-12-20 08:00:00',
        'enrollment_close_at' => '2026-01-20 17:00:00',
        'is_active' => true,
    ]);

    return CourseOffering::create([
        'course_id' => $course->id,
        'academic_period_id' => $period->id,
        'capacity' => 40,
        'price' => 450000,
        'discount_price' => 400000,
        'is_active' => true,
    ]);
}

function createReportOrderWithTransaction(array $overrides = []): array
{
    $student = $overrides['student'] ?? createReportStudent([
        'fullname' => 'Student Report Alpha',
        'email' => 'student-report-alpha@example.com',
    ]);
    $instructor = $overrides['instructor'] ?? createReportInstructor();
    $offering = $overrides['offering'] ?? createReportCourseOffering($instructor, $overrides['prefix'] ?? 'Alpha');

    $order = Order::create([
        'user_id' => $student->id,
        'voucher_id' => null,
        'order_code' => $overrides['order_code'] ?? 'ORD-REPORT-001',
        'subtotal' => $overrides['subtotal'] ?? 400000,
        'discount' => $overrides['discount'] ?? 0,
        'tax' => 0,
        'admin_fee' => 0,
        'note' => $overrides['note'] ?? 'Catatan laporan admin',
        'grand_total' => $overrides['grand_total'] ?? 400000,
        'status' => $overrides['order_status'] ?? 'completed',
        'created_at' => $overrides['order_created_at'] ?? '2026-05-24 08:00:00',
        'updated_at' => $overrides['order_created_at'] ?? '2026-05-24 08:00:00',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'course_offering_id' => $offering->id,
        'price' => $overrides['item_price'] ?? 400000,
    ]);

    $transaction = Transaction::create([
        'order_id' => $order->id,
        'invoice_code' => $overrides['invoice_code'] ?? 'INV-REPORT-001',
        'external_id' => $overrides['external_id'] ?? 'EXT-REPORT-001',
        'payment_method' => $overrides['payment_method'] ?? 'manual',
        'payment_channel' => $overrides['payment_channel'] ?? 'bank_transfer',
        'payment_url' => null,
        'payment_reference' => $overrides['payment_reference'] ?? 'REF-REPORT-001',
        'amount' => $overrides['amount'] ?? 400000,
        'status' => $overrides['transaction_status'] ?? 'success',
        'paid_at' => $overrides['paid_at'] ?? '2026-05-24 09:15:00',
        'expired_at' => $overrides['expired_at'] ?? '2026-05-25 09:15:00',
        'created_at' => $overrides['transaction_created_at'] ?? '2026-05-24 09:00:00',
        'updated_at' => $overrides['transaction_created_at'] ?? '2026-05-24 09:00:00',
    ]);

    return [$student, $order, $transaction, $offering];
}

it('exports filtered admin orders report as csv', function () {
    $admin = createReportAdmin();
    Sanctum::actingAs($admin);

    [$student] = createReportOrderWithTransaction([
        'prefix' => 'Matching',
        'order_code' => 'ORD-MATCH-001',
        'invoice_code' => 'INV-MATCH-001',
    ]);

    createReportOrderWithTransaction([
        'student' => createReportStudent([
            'fullname' => 'Student Report Beta',
            'email' => 'student-report-beta@example.com',
        ]),
        'prefix' => 'Ignored',
        'order_code' => 'ORD-IGNORE-001',
        'invoice_code' => 'INV-IGNORE-001',
        'payment_reference' => 'REF-IGNORE-001',
    ]);

    $response = $this->get('/api/admin/orders/export?search=' . urlencode($student->fullname));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('content-disposition'))->toContain('admin-orders-report-');

    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent());

    expect($csv)->toContain('Kode Order')
        ->toContain('ORD-MATCH-001')
        ->toContain('Matching Course')
        ->toContain($student->fullname)
        ->not->toContain('ORD-IGNORE-001');
});

it('exports filtered admin transactions report as csv', function () {
    $admin = createReportAdmin();
    Sanctum::actingAs($admin);

    [$student, $order, $transaction] = createReportOrderWithTransaction([
        'prefix' => 'Success',
        'order_code' => 'ORD-SUCCESS-001',
        'invoice_code' => 'INV-SUCCESS-001',
        'payment_reference' => 'REF-SUCCESS-001',
        'transaction_status' => 'success',
    ]);

    createReportOrderWithTransaction([
        'student' => createReportStudent([
            'fullname' => 'Student Failed Export',
            'email' => 'student-failed-export@example.com',
        ]),
        'prefix' => 'Failed',
        'order_code' => 'ORD-FAILED-001',
        'invoice_code' => 'INV-FAILED-001',
        'payment_reference' => 'REF-FAILED-001',
        'transaction_status' => 'failed',
    ]);

    $response = $this->get('/api/admin/transactions/export?search=' . urlencode($student->fullname) . '&status=success');

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('content-disposition'))->toContain('admin-transactions-report-');

    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent());

    expect($csv)->toContain('Kode Invoice')
        ->toContain($transaction->invoice_code)
        ->toContain($order->order_code)
        ->toContain($student->fullname)
        ->toContain('success')
        ->not->toContain('INV-FAILED-001');
});
