<?php

use App\Jobs\SendPushNotificationJob;
use App\Models\AcademicPeriod;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\ForumService;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createInstructorNotificationRole(string $name, int $id): Role
{
    return Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => $id], ['name' => $name]));
}

function createInstructorNotificationUser(string $roleName, string $email): User
{
    $roleId = match ($roleName) {
        'admin' => 1,
        'user' => 2,
        'instructor' => 3,
        default => 4,
    };

    $role = createInstructorNotificationRole($roleName, $roleId);

    return User::factory()->create([
        'role_id' => $role->id,
        'email' => $email,
    ]);
}

function createInstructorNotificationFixture(): array
{
    $admin = createInstructorNotificationUser('admin', 'course-notif-admin@example.com');
    $student = createInstructorNotificationUser('user', 'course-notif-student@example.com');
    $instructor = createInstructorNotificationUser('instructor', 'course-notif-instructor@example.com');
    $otherInstructor = createInstructorNotificationUser('instructor', 'course-notif-other-instructor@example.com');

    $category = Category::query()->create([
        'name' => 'Notification Category',
        'slug' => 'notification-category',
    ]);

    $course = Course::query()->create([
        'category_id' => $category->id,
        'instructor_id' => $instructor->id,
        'title' => 'Notification Course',
        'slug' => 'notification-course',
        'description' => 'Course used for notification tests.',
    ]);

    $period = AcademicPeriod::query()->create([
        'code' => 'NOTIF-2026',
        'name' => 'Notification Period',
        'start_at' => now()->subWeek(),
        'end_at' => now()->addMonth(),
        'enrollment_open_at' => now()->subWeek(),
        'enrollment_close_at' => now()->addWeek(),
        'is_active' => true,
    ]);

    $offering = CourseOffering::query()->create([
        'course_id' => $course->id,
        'academic_period_id' => $period->id,
        'capacity' => 20,
        'price' => 150000,
        'discount_price' => null,
        'is_active' => true,
    ]);

    $enrollment = Enrollment::query()->create([
        'user_id' => $student->id,
        'course_offering_id' => $offering->id,
        'progress' => 100,
        'status' => 'active',
        'started_at' => now()->subDay(),
        'ended_at' => now()->addDay(),
    ]);

    return compact('admin', 'student', 'instructor', 'otherInstructor', 'course', 'period', 'offering', 'enrollment');
}

function enrollNotificationUser(User $user, CourseOffering $offering): Enrollment
{
    return Enrollment::query()->create([
        'user_id' => $user->id,
        'course_offering_id' => $offering->id,
        'progress' => 0,
        'status' => 'active',
        'started_at' => now()->subDay(),
        'ended_at' => now()->addDay(),
    ]);
}

it('notifies course instructor and admins when a student submits an assignment', function () {
    Queue::fake();
    $fixture = createInstructorNotificationFixture();
    $section = Section::query()->create([
        'course_id' => $fixture['course']->id,
        'title' => 'Notification Section',
        'sort_order' => 1,
    ]);

    $assignment = Assignment::query()->create([
        'course_id' => $fixture['course']->id,
        'section_id' => $section->id,
        'created_by' => $fixture['instructor']->id,
        'title' => 'Notification Assignment',
        'status' => 'published',
    ]);

    $submission = app(AssignmentService::class)->submitForEnrollment(
        $fixture['student']->id,
        $fixture['enrollment']->id,
        $assignment->id,
        ['submission_text' => 'Jawaban assignment']
    );

    $route = "/admin/course-activity/assignment-reviews?offeringId={$fixture['offering']->id}&submissionId={$submission->id}";

    foreach ([$fixture['admin'], $fixture['instructor']] as $recipient) {
        $notification = Notification::query()
            ->where('user_id', $recipient->id)
            ->where('type', 'assignment.submitted')
            ->first();

        expect($notification)->not()->toBeNull();
        expect($notification->reference_type)->toBe('assignment_submission');
        expect($notification->reference_id)->toBe($submission->id);
        expect($notification->actor_id)->toBe($fixture['student']->id);
        expect($notification->data)->toMatchArray([
            'course_id' => $fixture['course']->id,
            'course_offering_id' => $fixture['offering']->id,
            'academic_period_id' => $fixture['period']->id,
            'assignment_id' => $assignment->id,
            'submission_id' => $submission->id,
            'enrollment_id' => $fixture['enrollment']->id,
            'student_id' => $fixture['student']->id,
            'route' => $route,
        ]);
    }

    expect(Notification::query()->where('user_id', $fixture['otherInstructor']->id)->where('type', 'assignment.submitted')->exists())->toBeFalse();
    expect(Notification::query()->where('user_id', $fixture['student']->id)->where('type', 'assignment.submitted')->exists())->toBeFalse();
    Queue::assertPushed(SendPushNotificationJob::class, 2);
});

it('notifies course instructor and admins for student forum posts and replies', function () {
    Queue::fake();
    $fixture = createInstructorNotificationFixture();

    $post = app(ForumService::class)->createPost($fixture['course']->id, $fixture['student']->id, [
        'title' => 'Topik baru',
        'content' => 'Pertanyaan forum',
    ]);

    $reply = app(ForumService::class)->createReply($fixture['course']->id, $post->id, $fixture['student']->id, [
        'content' => 'Balasan forum',
    ]);

    foreach ([$fixture['admin'], $fixture['instructor']] as $recipient) {
        expect(Notification::query()
            ->where('user_id', $recipient->id)
            ->where('type', 'forum.posted')
            ->where('reference_id', $post->id)
            ->exists())->toBeTrue();

        $replyNotification = Notification::query()
            ->where('user_id', $recipient->id)
            ->where('type', 'forum.replied')
            ->where('reference_id', $reply->id)
            ->first();

        expect($replyNotification)->not()->toBeNull();
        expect($replyNotification->data)->toMatchArray([
            'course_id' => $fixture['course']->id,
            'post_id' => $post->id,
            'reply_id' => $reply->id,
            'student_id' => $fixture['student']->id,
            'route' => "/admin/course-activity/forum?courseId={$fixture['course']->id}&forumPostId={$post->id}",
        ]);
    }

    expect(Notification::query()->where('user_id', $fixture['otherInstructor']->id)->whereIn('type', ['forum.posted', 'forum.replied'])->exists())->toBeFalse();
    expect(Notification::query()->where('user_id', $fixture['student']->id)->whereIn('type', ['forum.posted', 'forum.replied', 'forum.reply.received'])->exists())->toBeFalse();
    Queue::assertPushed(SendPushNotificationJob::class, 4);
});

it('notifies forum post owners when another student replies', function () {
    Queue::fake();
    $fixture = createInstructorNotificationFixture();
    $replyingStudent = createInstructorNotificationUser('user', 'course-notif-second-student@example.com');
    enrollNotificationUser($replyingStudent, $fixture['offering']);

    $post = app(ForumService::class)->createPost($fixture['course']->id, $fixture['student']->id, [
        'title' => 'Topik yang menunggu jawaban',
        'content' => 'Mohon bantu jelaskan materi ini.',
    ]);

    $reply = app(ForumService::class)->createReply($fixture['course']->id, $post->id, $replyingStudent->id, [
        'content' => 'Saya bantu jawab dari catatan saya.',
    ]);

    $notification = Notification::query()
        ->where('user_id', $fixture['student']->id)
        ->where('type', 'forum.reply.received')
        ->where('reference_id', $reply->id)
        ->first();

    expect($notification)->not()->toBeNull();
    expect($notification->reference_type)->toBe('forum_reply');
    expect($notification->actor_id)->toBe($replyingStudent->id);
    expect($notification->data)->toMatchArray([
        'course_id' => $fixture['course']->id,
        'post_id' => $post->id,
        'reply_id' => $reply->id,
        'post_owner_id' => $fixture['student']->id,
        'replier_id' => $replyingStudent->id,
        'route' => "/student/enrollments/{$fixture['enrollment']->id}/forum/{$post->id}",
    ]);

    expect(Notification::query()
        ->where('user_id', $replyingStudent->id)
        ->where('type', 'forum.reply.received')
        ->exists())->toBeFalse();
    Queue::assertPushed(SendPushNotificationJob::class, 5);
});

it('notifies forum post owners when an instructor replies', function () {
    Queue::fake();
    $fixture = createInstructorNotificationFixture();

    $post = app(ForumService::class)->createPost($fixture['course']->id, $fixture['student']->id, [
        'title' => 'Butuh arahan instructor',
        'content' => 'Apakah ada referensi tambahan untuk materi ini?',
    ]);

    $reply = app(ForumService::class)->createReplyForAdmin($fixture['course']->id, $post->id, $fixture['instructor'], [
        'content' => 'Silakan mulai dari lesson 2 lalu cek assignment contohnya.',
    ]);

    $notification = Notification::query()
        ->where('user_id', $fixture['student']->id)
        ->where('type', 'forum.reply.received')
        ->where('reference_id', $reply->id)
        ->first();

    expect($notification)->not()->toBeNull();
    expect($notification->reference_type)->toBe('forum_reply');
    expect($notification->actor_id)->toBe($fixture['instructor']->id);
    expect($notification->data)->toMatchArray([
        'course_id' => $fixture['course']->id,
        'post_id' => $post->id,
        'reply_id' => $reply->id,
        'post_owner_id' => $fixture['student']->id,
        'replier_id' => $fixture['instructor']->id,
        'route' => "/student/enrollments/{$fixture['enrollment']->id}/forum/{$post->id}",
    ]);

    Queue::assertPushed(SendPushNotificationJob::class, 3);
});

it('notifies admins when a student creates a course review', function () {
    Queue::fake();
    $fixture = createInstructorNotificationFixture();
    $fixture['enrollment']->update([
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $review = app(ReviewService::class)->createReview($fixture['course']->id, $fixture['student'], [
        'rating' => 5,
        'review' => 'Course sangat membantu.',
    ]);

    foreach ([$fixture['admin']] as $recipient) {
        $notification = Notification::query()
            ->where('user_id', $recipient->id)
            ->where('type', 'course.review.created')
            ->first();

        expect($notification)->not()->toBeNull();
        expect($notification->reference_type)->toBe('review');
        expect($notification->reference_id)->toBe($review->id);
        expect($notification->data)->toMatchArray([
            'course_id' => $fixture['course']->id,
            'review_id' => $review->id,
            'student_id' => $fixture['student']->id,
            'route' => "/admin/course-reviews?courseId={$fixture['course']->id}&reviewId={$review->id}",
        ]);
    }

    expect(Notification::query()->where('user_id', $fixture['otherInstructor']->id)->where('type', 'course.review.created')->exists())->toBeFalse();
    expect(Notification::query()->where('user_id', $fixture['instructor']->id)->where('type', 'course.review.created')->exists())->toBeFalse();
    expect(Notification::query()->where('user_id', $fixture['student']->id)->where('type', 'course.review.created')->exists())->toBeFalse();
    Queue::assertPushed(SendPushNotificationJob::class, 1);
});

it('rejects instructors from reading reviews outside their course', function () {
    $fixture = createInstructorNotificationFixture();
    Sanctum::actingAs($fixture['otherInstructor']);

    $this->getJson("/api/admin/courses/{$fixture['course']->id}/reviews")
        ->assertForbidden();
});
