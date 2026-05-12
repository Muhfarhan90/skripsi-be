<?php

use App\Models\Category;
use App\Models\Course;
use App\Models\Option;
use App\Models\Question;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createAdminUser(): User
{
    $role = Role::create(['name' => 'admin']);

    return User::factory()->create([
        'role_id' => $role->id,
        'email' => 'admin@example.com',
    ]);
}

function createCourseWithSection(string $prefix, User $instructor): array
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

    $section = Section::create([
        'course_id' => $course->id,
        'title' => "{$prefix} Section",
        'sort_order' => 1,
    ]);

    return [$course, $section];
}

it('creates and lists quizzes scoped to a course section', function () {
    $admin = createAdminUser();
    Sanctum::actingAs($admin);

    [$courseA, $sectionA] = createCourseWithSection('Alpha', $admin);
    [$courseB] = createCourseWithSection('Beta', $admin);

    $createResponse = $this->postJson("/api/admin/courses/{$courseA->id}/sections/{$sectionA->id}/quizzes", [
        'title' => 'Quiz Alpha',
        'description' => 'Desc',
        'duration' => 30,
        'passing_score' => 75,
        'weight' => 10,
        'is_active' => true,
        'is_random' => true,
        'max_attempts' => 3,
    ]);

    $createResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.course_id', $courseA->id)
        ->assertJsonPath('data.section_id', $sectionA->id)
        ->assertJsonPath('data.title', 'Quiz Alpha');

    $quizId = $createResponse->json('data.id');
    expect($quizId)->not->toBeNull();

    $this->assertDatabaseHas('quizzes', [
        'id' => $quizId,
        'course_id' => $courseA->id,
        'section_id' => $sectionA->id,
        'is_random' => 1,
    ]);

    $this->getJson("/api/admin/courses/{$courseA->id}/quizzes")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $quizId);

    $this->getJson("/api/admin/courses/{$courseB->id}/quizzes")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(0, 'data');
});

it('rejects creating quiz on a section outside the selected course', function () {
    $admin = createAdminUser();
    Sanctum::actingAs($admin);

    [$courseA] = createCourseWithSection('Gamma', $admin);
    [, $sectionB] = createCourseWithSection('Delta', $admin);

    $this->postJson("/api/admin/courses/{$courseA->id}/sections/{$sectionB->id}/quizzes", [
        'title' => 'Invalid Quiz',
        'duration' => 20,
        'passing_score' => 70,
        'weight' => 10,
        'is_active' => true,
        'is_random' => false,
    ])->assertStatus(422)
      ->assertJsonPath('success', false);
});

it('handles scoped question CRUD under quiz route', function () {
    $admin = createAdminUser();
    Sanctum::actingAs($admin);

    [$course, $section] = createCourseWithSection('Epsilon', $admin);

    $quizResponse = $this->postJson("/api/admin/courses/{$course->id}/sections/{$section->id}/quizzes", [
        'title' => 'Quiz Epsilon',
        'duration' => 30,
        'passing_score' => 70,
        'weight' => 10,
        'is_active' => true,
        'is_random' => false,
    ]);
    $quizId = $quizResponse->json('data.id');

    $questionResponse = $this->postJson("/api/admin/quizzes/{$quizId}/questions", [
        'question_text' => '2 + 2 = ?',
        'type' => 'multiple_choice',
        'score' => 10,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $questionId = $questionResponse->json('data.id');

    $questionResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.quiz_id', $quizId)
        ->assertJsonPath('data.score', 100);

    $secondQuestionId = $this->postJson("/api/admin/quizzes/{$quizId}/questions", [
        'question_text' => '5 + 5 = ?',
        'type' => 'multiple_choice',
        'is_active' => true,
    ])->json('data.id');

    $this->getJson("/api/admin/quizzes/{$quizId}")
        ->assertOk()
        ->assertJsonPath('data.questions.0.score', 50)
        ->assertJsonPath('data.questions.1.score', 50);

    $this->putJson("/api/admin/quizzes/{$quizId}/questions/reorder", [
        'question_ids' => [$secondQuestionId, $questionId],
    ])->assertOk()
      ->assertJsonPath('success', true);

    $this->getJson("/api/admin/quizzes/{$quizId}")
        ->assertOk()
        ->assertJsonPath('data.questions.0.id', $secondQuestionId)
        ->assertJsonPath('data.questions.1.id', $questionId);

    $this->putJson("/api/admin/quizzes/{$quizId}/questions/{$questionId}", [
        'question_text' => '3 + 3 = ?',
        'type' => 'multiple_choice',
        'score' => 20,
        'sort_order' => 1,
        'is_active' => true,
    ])->assertOk()
      ->assertJsonPath('data.question_text', '3 + 3 = ?');

    $otherQuizId = $this->postJson("/api/admin/courses/{$course->id}/sections/{$section->id}/quizzes", [
        'title' => 'Quiz Other',
        'duration' => 15,
        'passing_score' => 60,
        'weight' => 5,
        'is_active' => true,
        'is_random' => false,
    ])->json('data.id');

    $this->putJson("/api/admin/quizzes/{$otherQuizId}/questions/{$questionId}", [
        'question_text' => 'Should fail',
    ])->assertStatus(404);

    $this->deleteJson("/api/admin/quizzes/{$quizId}/questions/{$questionId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('questions', [
        'id' => $questionId,
    ]);
});

it('handles scoped option CRUD under question route', function () {
    $admin = createAdminUser();
    Sanctum::actingAs($admin);

    [$course, $section] = createCourseWithSection('Zeta', $admin);
    $quizId = $this->postJson("/api/admin/courses/{$course->id}/sections/{$section->id}/quizzes", [
        'title' => 'Quiz Zeta',
        'duration' => 20,
        'passing_score' => 70,
        'weight' => 10,
        'is_active' => true,
        'is_random' => false,
    ])->json('data.id');

    $questionId = $this->postJson("/api/admin/quizzes/{$quizId}/questions", [
        'question_text' => 'Question A',
        'type' => 'multiple_choice',
        'score' => 10,
        'sort_order' => 1,
        'is_active' => true,
    ])->json('data.id');

    $optionResponse = $this->postJson("/api/admin/questions/{$questionId}/options", [
        'option_text' => 'Option A',
        'is_correct' => true,
    ]);
    $optionId = $optionResponse->json('data.id');

    $optionResponse->assertOk()
        ->assertJsonPath('data.question_id', $questionId);

    $this->putJson("/api/admin/questions/{$questionId}/options/{$optionId}", [
        'option_text' => 'Option Updated',
        'is_correct' => false,
    ])->assertOk()
      ->assertJsonPath('data.option_text', 'Option Updated');

    $otherQuestion = Question::create([
        'quiz_id' => $quizId,
        'question_text' => 'Question B',
        'type' => 'multiple_choice',
        'score' => 10,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $this->putJson("/api/admin/questions/{$otherQuestion->id}/options/{$optionId}", [
        'option_text' => 'Should fail',
    ])->assertStatus(404);

    $this->deleteJson("/api/admin/questions/{$questionId}/options/{$optionId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('options', [
        'id' => $optionId,
    ]);
});

it('returns nested quiz detail and deletes related question/options on quiz delete', function () {
    $admin = createAdminUser();
    Sanctum::actingAs($admin);

    [$course, $sectionA] = createCourseWithSection('Eta', $admin);
    $sectionB = Section::create([
        'course_id' => $course->id,
        'title' => 'Eta Section 2',
        'sort_order' => 2,
    ]);

    $quizId = $this->postJson("/api/admin/courses/{$course->id}/sections/{$sectionA->id}/quizzes", [
        'title' => 'Quiz Eta',
        'duration' => 25,
        'passing_score' => 70,
        'weight' => 10,
        'is_active' => true,
        'is_random' => false,
    ])->json('data.id');

    $questionId = $this->postJson("/api/admin/quizzes/{$quizId}/questions", [
        'question_text' => 'Question Eta',
        'type' => 'multiple_choice',
        'score' => 5,
        'sort_order' => 1,
        'is_active' => true,
    ])->json('data.id');

    $optionId = $this->postJson("/api/admin/questions/{$questionId}/options", [
        'option_text' => 'Option Eta',
        'is_correct' => true,
    ])->json('data.id');

    $this->getJson("/api/admin/quizzes/{$quizId}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $quizId)
        ->assertJsonPath('data.questions.0.id', $questionId)
        ->assertJsonPath('data.questions.0.options.0.id', $optionId);

    $this->putJson("/api/admin/courses/{$course->id}/sections/{$sectionB->id}/quizzes/{$quizId}", [
        'title' => 'Quiz Eta Updated',
        'duration' => 40,
        'passing_score' => 80,
        'weight' => 20,
        'is_active' => true,
        'is_random' => true,
    ])->assertOk()
      ->assertJsonPath('data.section_id', $sectionB->id)
      ->assertJsonPath('data.title', 'Quiz Eta Updated');

    $this->deleteJson("/api/admin/quizzes/{$quizId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('quizzes', ['id' => $quizId]);
    $this->assertSoftDeleted('questions', ['id' => $questionId]);
    $this->assertDatabaseMissing('options', ['id' => $optionId]);
});
