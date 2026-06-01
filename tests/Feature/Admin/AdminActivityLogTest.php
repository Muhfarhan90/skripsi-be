<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createActivityLogRole(int $id, string $name): Role
{
    return Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => $id], ['name' => $name]));
}

function createActivityLogUser(int $roleId, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role_id' => $roleId,
    ], $attributes));
}

it('lists admin activity logs with pagination metadata', function () {
    $adminRole = createActivityLogRole(1, 'admin');
    $studentRole = createActivityLogRole(2, 'student');

    $admin = createActivityLogUser($adminRole->id, [
        'fullname' => 'Admin Logger',
        'email' => 'admin-logger@example.com',
    ]);
    $student = createActivityLogUser($studentRole->id, [
        'fullname' => 'Student Logged',
        'email' => 'student-logged@example.com',
    ]);

    Sanctum::actingAs($admin);

    $this->putJson("/api/admin/users/{$student->id}", [
        'fullname' => 'Student Updated',
    ])->assertOk();

    $response = $this->getJson('/api/admin/activity-logs?per_page=10');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('data.0.actor', 'Admin Logger')
        ->assertJsonPath('data.0.event', 'updated')
        ->assertJsonPath('data.0.subject_label', 'User')
        ->assertJsonPath('data.0.subject_name', 'Student Updated')
        ->assertJsonPath('data.0.activity', 'User Student Updated diperbarui');
});

it('filters admin activity logs by event and search keyword', function () {
    $adminRole = createActivityLogRole(1, 'admin');
    $studentRole = createActivityLogRole(2, 'student');

    $admin = createActivityLogUser($adminRole->id, [
        'fullname' => 'Admin Filter',
        'email' => 'admin-filter@example.com',
    ]);
    $student = createActivityLogUser($studentRole->id, [
        'fullname' => 'Target Search Student',
        'email' => 'target-search-student@example.com',
    ]);

    Sanctum::actingAs($admin);

    $this->putJson("/api/admin/users/{$student->id}", [
        'fullname' => 'Target Search Student Updated',
    ])->assertOk();

    $response = $this->getJson('/api/admin/activity-logs?event=updated&search=Target%20Search%20Student%20Updated');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event', 'updated')
        ->assertJsonPath('data.0.subject_name', 'Target Search Student Updated');
});
