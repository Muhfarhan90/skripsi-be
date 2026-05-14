<?php

use App\Models\AcademicPeriod;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createAdminOfferingManager(): User
{
    $role = Role::create(['name' => 'admin']);

    return User::factory()->create([
        'role_id' => $role->id,
        'email' => 'offering-admin@example.com',
    ]);
}

function createManagedCourse(User $instructor, string $prefix = 'Offering'): Course
{
    $category = Category::create([
        'name' => "{$prefix} Category",
        'slug' => Str::slug("{$prefix}-category"),
        'description' => null,
    ]);

    return Course::create([
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
}

function createManagedPeriod(string $code, array $overrides = []): AcademicPeriod
{
    $defaults = [
        'code' => $code,
        'name' => "{$code} Name",
        'start_at' => '2026-01-10 09:00:00',
        'end_at' => '2026-04-10 17:00:00',
        'enrollment_open_at' => '2025-12-20 08:00:00',
        'enrollment_close_at' => '2026-01-20 17:00:00',
        'is_active' => false,
    ];

    return AcademicPeriod::create(array_merge($defaults, $overrides));
}

it('handles academic period CRUD and prevents deleting periods with offerings', function () {
    $admin = createAdminOfferingManager();
    Sanctum::actingAs($admin);

    $createResponse = $this->postJson('/api/admin/academic-periods', [
        'code' => 'PRE-U-2026-C',
        'name' => 'Pre-University Period C 2026',
        'start_at' => '2026-05-01 08:00:00',
        'end_at' => '2026-08-30 17:00:00',
        'enrollment_open_at' => '2026-04-01 08:00:00',
        'enrollment_close_at' => '2026-05-10 17:00:00',
        'is_active' => false,
    ]);

    $createResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.code', 'PRE-U-2026-C')
        ->assertJsonPath('data.is_active', false);

    $periodId = $createResponse->json('data.id');
    expect($periodId)->not->toBeNull();

    $this->putJson("/api/admin/academic-periods/{$periodId}", [
        'code' => 'PRE-U-2026-C',
        'name' => 'Pre-University Period C Updated',
        'start_at' => '2026-05-01 08:00:00',
        'end_at' => '2026-08-31 18:00:00',
        'enrollment_open_at' => '2026-04-01 08:00:00',
        'enrollment_close_at' => '2026-05-12 17:00:00',
        'is_active' => true,
    ])->assertOk()
      ->assertJsonPath('data.name', 'Pre-University Period C Updated')
      ->assertJsonPath('data.is_active', true);

    $course = createManagedCourse($admin, 'Period Guard');

    CourseOffering::create([
        'course_id' => $course->id,
        'academic_period_id' => $periodId,
        'title' => 'Period Guard Offering',
        'capacity' => 40,
        'price' => 250000,
        'discount_price' => 225000,
        'is_active' => false,
    ]);

    $this->deleteJson("/api/admin/academic-periods/{$periodId}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    CourseOffering::query()->where('academic_period_id', $periodId)->delete();

    $this->deleteJson("/api/admin/academic-periods/{$periodId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('academic_periods', [
        'id' => $periodId,
    ]);
});

it('handles course offering CRUD, enforces unique course-period offerings, and blocks delete when enrolled', function () {
    $admin = createAdminOfferingManager();
    Sanctum::actingAs($admin);

    $course = createManagedCourse($admin, 'Offering CRUD');
    $period = createManagedPeriod('PRE-U-2026-D', [
        'name' => 'Pre-University Period D 2026',
        'start_at' => '2026-06-01 08:00:00',
        'end_at' => '2026-09-01 17:00:00',
        'enrollment_open_at' => '2026-05-01 08:00:00',
        'enrollment_close_at' => '2026-06-10 17:00:00',
        'is_active' => true,
    ]);

    $createResponse = $this->postJson('/api/admin/course-offerings', [
        'course_id' => $course->id,
        'academic_period_id' => $period->id,
        'title' => 'Intro Programming - Cohort Z 2026',
        'capacity' => 35,
        'price' => 550000,
        'discount_price' => 500000,
        'is_active' => false,
    ]);

    $createResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Intro Programming - Cohort Z 2026')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.academic_period.start_at', '2026-06-01 08:00:00');

    $offeringId = $createResponse->json('data.id');
    expect($offeringId)->not->toBeNull();

    $this->postJson('/api/admin/course-offerings', [
        'course_id' => $course->id,
        'academic_period_id' => $period->id,
        'title' => 'Duplicate Intro Programming Offering',
        'capacity' => 30,
        'price' => 500000,
        'discount_price' => 450000,
        'is_active' => false,
    ])->assertStatus(422)
      ->assertJsonPath('success', false);

    $this->putJson("/api/admin/course-offerings/{$offeringId}", [
        'course_id' => $course->id,
        'academic_period_id' => $period->id,
        'title' => 'Intro Programming - Cohort Z 2026 Updated',
        'capacity' => 45,
        'price' => 600000,
        'discount_price' => 540000,
        'is_active' => true,
    ])->assertOk()
      ->assertJsonPath('data.title', 'Intro Programming - Cohort Z 2026 Updated')
      ->assertJsonPath('data.is_active', true);

    Enrollment::create([
        'user_id' => $admin->id,
        'course_offering_id' => $offeringId,
        'order_id' => null,
        'last_lesson_id' => null,
        'progress' => 0,
        'status' => 'active',
        'completed_at' => null,
        'started_at' => now(),
        'ended_at' => null,
        'expired_at' => null,
    ]);

    $this->deleteJson("/api/admin/course-offerings/{$offeringId}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    Enrollment::query()->where('course_offering_id', $offeringId)->delete();

    $this->deleteJson("/api/admin/course-offerings/{$offeringId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('course_offerings', [
        'id' => $offeringId,
    ]);
});
