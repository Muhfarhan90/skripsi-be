<?php

use App\Models\Category;
use App\Models\Course;
use App\Models\Role;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createSkillAdminUser(): User
{
    $role = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 1], ['name' => 'admin']));

    return User::factory()->create([
        'role_id' => $role->id,
        'email' => 'skill-admin@example.com',
    ]);
}

function createSkillCourseCategory(string $name = 'Skill Category'): Category
{
    return Category::create([
        'name' => $name,
        'slug' => Str::slug($name),
        'description' => null,
    ]);
}

it('handles skill CRUD and protects skills that are still attached to courses', function () {
    $admin = createSkillAdminUser();
    Sanctum::actingAs($admin);

    $createResponse = $this->postJson('/api/admin/skills', [
        'name' => 'Critical Thinking',
        'is_active' => true,
    ]);

    $createResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Critical Thinking')
        ->assertJsonPath('data.slug', 'critical-thinking')
        ->assertJsonPath('data.is_active', true);

    $skillId = $createResponse->json('data.id');
    expect($skillId)->not->toBeNull();

    $this->putJson("/api/admin/skills/{$skillId}", [
        'name' => 'Critical Thinking Advanced',
        'is_active' => false,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Critical Thinking Advanced')
        ->assertJsonPath('data.is_active', false);

    $category = createSkillCourseCategory();

    $courseResponse = $this->postJson('/api/admin/courses', [
        'title' => 'Reasoning Foundations',
        'description' => 'Course description',
        'category_id' => $category->id,
        'instructor_id' => $admin->id,
        'requirements' => null,
        'outcomes' => null,
        'skill_ids' => [$skillId],
    ]);

    $courseResponse->assertOk()
        ->assertJsonPath('data.title', 'Reasoning Foundations')
        ->assertJsonCount(1, 'data.skills')
        ->assertJsonPath('data.skills.0.id', $skillId);

    $courseId = $courseResponse->json('data.id');
    expect($courseId)->not->toBeNull();

    $this->assertDatabaseHas('course_skills', [
        'course_id' => $courseId,
        'skill_id' => $skillId,
        'sort_order' => 1,
    ]);

    $this->deleteJson("/api/admin/skills/{$skillId}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->putJson("/api/admin/courses/{$courseId}", [
        'skill_ids' => [],
    ])->assertOk()
        ->assertJsonCount(0, 'data.skills');

    $this->deleteJson("/api/admin/skills/{$skillId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('skills', [
        'id' => $skillId,
    ]);
});

it('syncs multiple skills to a course in the selected order', function () {
    $admin = createSkillAdminUser();
    Sanctum::actingAs($admin);

    $category = createSkillCourseCategory('Ordering Category');

    $skillA = Skill::create([
        'name' => 'Communication',
        'slug' => 'communication',
        'is_active' => true,
    ]);

    $skillB = Skill::create([
        'name' => 'Leadership',
        'slug' => 'leadership',
        'is_active' => true,
    ]);

    $course = Course::create([
        'title' => 'Team Essentials',
        'slug' => 'team-essentials',
        'description' => 'Course description',
        'category_id' => $category->id,
        'instructor_id' => $admin->id,
        'thumbnail' => null,
        'total_duration' => 0,
        'requirements' => null,
        'outcomes' => null,
    ]);

    $this->putJson("/api/admin/courses/{$course->id}", [
        'skill_ids' => [$skillB->id, $skillA->id],
    ])->assertOk()
        ->assertJsonPath('data.skills.0.id', $skillB->id)
        ->assertJsonPath('data.skills.1.id', $skillA->id);

    $this->assertDatabaseHas('course_skills', [
        'course_id' => $course->id,
        'skill_id' => $skillB->id,
        'sort_order' => 1,
    ]);

    $this->assertDatabaseHas('course_skills', [
        'course_id' => $course->id,
        'skill_id' => $skillA->id,
        'sort_order' => 2,
    ]);
});

it('syncs selected skills when course master is saved through the curriculum endpoint', function () {
    $admin = createSkillAdminUser();
    Sanctum::actingAs($admin);

    $category = createSkillCourseCategory('Curriculum Skill Category');

    $skillA = Skill::create([
        'name' => 'Analytical Thinking',
        'slug' => 'analytical-thinking',
        'is_active' => true,
    ]);

    $skillB = Skill::create([
        'name' => 'Debugging',
        'slug' => 'debugging',
        'is_active' => true,
    ]);

    $course = Course::create([
        'title' => 'Curriculum Save Course',
        'slug' => 'curriculum-save-course',
        'description' => 'Course description',
        'category_id' => $category->id,
        'instructor_id' => $admin->id,
        'thumbnail' => null,
        'total_duration' => 0,
        'requirements' => null,
        'outcomes' => null,
    ]);

    $response = $this->putJson("/api/admin/courses/{$course->id}/curriculum", [
        'course' => [
            'skill_ids' => [$skillB->id, $skillA->id],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.skills.0.id', $skillB->id)
        ->assertJsonPath('data.skills.1.id', $skillA->id);

    $this->assertDatabaseHas('course_skills', [
        'course_id' => $course->id,
        'skill_id' => $skillB->id,
        'sort_order' => 1,
    ]);

    $this->assertDatabaseHas('course_skills', [
        'course_id' => $course->id,
        'skill_id' => $skillA->id,
        'sort_order' => 2,
    ]);
});
