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
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createDashboardRole(int $id, string $name): Role
{
    return Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => $id], ['name' => $name]));
}

function createDashboardUser(int $roleId, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role_id' => $roleId,
    ], $attributes));
}

function createDashboardOffering(User $instructor, string $prefix = 'Dashboard'): CourseOffering
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
        'start_at' => '2026-05-01 08:00:00',
        'end_at' => '2026-08-31 17:00:00',
        'enrollment_open_at' => '2026-04-01 08:00:00',
        'enrollment_close_at' => '2026-05-31 17:00:00',
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

afterEach(function () {
    Carbon::setTestNow();
});

it('returns real admin dashboard metrics from database data', function () {
    Carbon::setTestNow('2026-05-25 10:00:00');

    $adminRole = createDashboardRole(1, 'admin');
    $studentRole = createDashboardRole(2, 'student');
    $instructorRole = createDashboardRole(3, 'instructor');

    $admin = createDashboardUser($adminRole->id, [
        'fullname' => 'Admin Dashboard',
        'email' => 'admin-dashboard@example.com',
    ]);
    $instructor = createDashboardUser($instructorRole->id, [
        'fullname' => 'Instructor Dashboard',
        'email' => 'instructor-dashboard@example.com',
    ]);
    $student = createDashboardUser($studentRole->id, [
        'fullname' => 'Student Dashboard',
        'email' => 'student-dashboard@example.com',
    ]);
    createDashboardUser($studentRole->id, [
        'fullname' => 'Student Inactive',
        'email' => 'student-inactive@example.com',
        'is_active' => false,
    ]);

    $offering = createDashboardOffering($instructor);

    $order = Order::create([
        'user_id' => $student->id,
        'voucher_id' => null,
        'order_code' => 'ORD-DASH-001',
        'subtotal' => 400000,
        'discount' => 0,
        'tax' => 0,
        'admin_fee' => 0,
        'note' => null,
        'grand_total' => 400000,
        'status' => 'completed',
        'created_at' => '2026-05-24 09:00:00',
        'updated_at' => '2026-05-24 09:00:00',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'course_offering_id' => $offering->id,
        'price' => 400000,
    ]);

    Transaction::create([
        'order_id' => $order->id,
        'invoice_code' => 'INV-DASH-001',
        'external_id' => 'EXT-DASH-001',
        'payment_method' => 'manual',
        'payment_channel' => 'bank_transfer',
        'payment_url' => null,
        'payment_reference' => 'REF-DASH-001',
        'amount' => 400000,
        'status' => 'success',
        'paid_at' => '2026-05-24 10:00:00',
        'expired_at' => '2026-05-25 10:00:00',
        'created_at' => '2026-05-24 09:30:00',
        'updated_at' => '2026-05-24 09:30:00',
    ]);

    Voucher::create([
        'code' => 'DASH-AKTIF',
        'discount_type' => 'percentage',
        'discount_amount' => 10,
        'min_purchase' => null,
        'max_discount' => null,
        'usage_limit' => null,
        'is_active' => true,
        'expired_at' => '2026-05-28 23:59:59',
    ]);

    Voucher::create([
        'code' => 'DASH-EXPIRED',
        'discount_type' => 'fixed',
        'discount_amount' => 20000,
        'min_purchase' => null,
        'max_discount' => null,
        'usage_limit' => null,
        'is_active' => true,
        'expired_at' => '2026-05-10 23:59:59',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/admin/dashboard');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.metrics.0.label', 'Total Users')
        ->assertJsonPath('data.metrics.0.value', '4')
        ->assertJsonPath('data.metrics.0.note', '3 akun aktif saat ini')
        ->assertJsonPath('data.metrics.1.label', 'Total Courses')
        ->assertJsonPath('data.metrics.1.value', '1')
        ->assertJsonPath('data.metrics.2.label', 'Transaksi Bulan Ini')
        ->assertJsonPath('data.metrics.2.value', '1')
        ->assertJsonPath('data.metrics.2.note', 'Rp400.000 transaksi sukses bulan ini')
        ->assertJsonPath('data.metrics.3.label', 'Voucher Aktif')
        ->assertJsonPath('data.metrics.3.value', '1')
        ->assertJsonPath('data.metrics.3.note', '1 voucher berakhir <= 7 hari');
});

it('returns recent admin activities from the audit log', function () {
    Carbon::setTestNow('2026-05-25 11:00:00');

    $adminRole = createDashboardRole(1, 'admin');
    $studentRole = createDashboardRole(2, 'student');

    $admin = createDashboardUser($adminRole->id, [
        'fullname' => 'Admin Audit',
        'email' => 'admin-audit@example.com',
    ]);
    $student = createDashboardUser($studentRole->id, [
        'fullname' => 'Student Before Update',
        'email' => 'student-before-update@example.com',
    ]);

    Sanctum::actingAs($admin);

    $this->putJson("/api/admin/users/{$student->id}", [
        'fullname' => 'Student After Update',
    ])->assertOk();

    $response = $this->getJson('/api/admin/dashboard');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.recent_activities.0.actor', 'Admin Audit')
        ->assertJsonPath('data.recent_activities.0.event', 'updated')
        ->assertJsonPath('data.recent_activities.0.subject_label', 'User')
        ->assertJsonPath('data.recent_activities.0.subject_name', 'Student After Update')
        ->assertJsonPath('data.recent_activities.0.activity', 'User Student After Update diperbarui');
});
