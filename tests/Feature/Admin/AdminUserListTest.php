<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function ensureUserListRole(string $name): Role
{
    return Role::unguarded(fn () => Role::query()->updateOrCreate(['name' => $name]));
}

function createManagedUser(string $roleName, array $attributes = []): User
{
    $role = ensureUserListRole($roleName);

    return User::factory()->create(array_merge([
        'role_id' => $role->id,
    ], $attributes));
}

it('filters student users and exposes school analysis fields', function () {
    $admin = createManagedUser('admin');
    $student = createManagedUser('user', [
        'fullname' => 'Student Buyer',
        'email' => 'student@example.com',
        'nisn' => '2026001',
        'school_origin' => 'SMAN 1 Jakarta',
    ]);
    createManagedUser('instructor', [
        'fullname' => 'Instructor User',
        'email' => 'instructor@example.com',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/admin/users?role_group=students&search=SMAN%201');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $student->id)
        ->assertJsonPath('data.0.role_name', 'user')
        ->assertJsonPath('data.0.nisn', '2026001')
        ->assertJsonPath('data.0.school_origin', 'SMAN 1 Jakarta');

    expect($response->json('data.0'))->toHaveKey('orders_count');
    expect($response->json('data.0.orders_count'))->toBe(0);
});

it('filters only instructor users into the instructors group', function () {
    $admin = createManagedUser('admin', [
        'fullname' => 'Platform Admin',
        'email' => 'admin@example.com',
    ]);
    $instructor = createManagedUser('instructor', [
        'fullname' => 'Teaching Staff',
        'email' => 'instructor@example.com',
    ]);
    $student = createManagedUser('user', [
        'fullname' => 'Registered Student',
        'email' => 'student@example.com',
        'school_origin' => 'SMKN 2 Bandung',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/admin/users?role_group=instructors');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $instructor->id)
        ->assertJsonPath('data.0.role_name', 'instructor');

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($instructor->id);
    expect($ids)->not->toContain($admin->id);
    expect($ids)->not->toContain($student->id);
});
