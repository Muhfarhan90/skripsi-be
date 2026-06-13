<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ForumPost;
use App\Models\ForumReply;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Review;
use App\Models\Transaction;
use App\Models\User;

class NotificationService
{
    public function getForUser(int $userId, int $perPage = 15)
    {
        return Notification::query()
            ->with('actor:id,fullname,email')
            ->where('user_id', $userId)
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->paginate(max($perPage, 1));
    }

    public function getUnreadCount(int $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(int $userId, int $notificationId): Notification
    {
        $notification = Notification::query()
            ->where('user_id', $userId)
            ->findOrFail($notificationId);

        if (! $notification->read_at) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        return $notification->fresh('actor:id,fullname,email');
    }

    public function markAllAsRead(int $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function publishTransactionSuccess(Transaction $transaction, iterable $enrollments = []): void
    {
        $transaction->loadMissing('order');
        $order = $transaction->order;

        if (! $order) {
            return;
        }

        $normalizedEnrollments = collect($enrollments)
            ->filter(fn ($enrollment) => $enrollment instanceof Enrollment)
            ->values();

        $this->saveUniqueNotification([
            'user_id' => $order->user_id,
            'type' => 'payment.success',
            'reference_type' => 'transaction',
            'reference_id' => $transaction->id,
        ], [
            'title' => 'Pembayaran berhasil',
            'body' => 'Pembayaran Anda berhasil diproses dan akses kelas sudah diperbarui.',
            'data' => [
                'transaction_id' => $transaction->id,
                'order_id' => $order->id,
                'enrollment_ids' => $normalizedEnrollments->pluck('id')->values()->all(),
                'route' => '/student/orders/' . $order->id,
            ],
            'sent_at' => now(),
        ]);

        foreach ($normalizedEnrollments as $enrollment) {
            $this->publishEnrollmentActivated($enrollment);
        }
    }

    public function publishTransactionFailed(Transaction $transaction): void
    {
        $transaction->loadMissing('order');
        $order = $transaction->order;

        if (! $order) {
            return;
        }

        $this->saveUniqueNotification([
            'user_id' => $order->user_id,
            'type' => 'payment.failed',
            'reference_type' => 'transaction',
            'reference_id' => $transaction->id,
        ], [
            'title' => 'Pembayaran gagal',
            'body' => 'Pembayaran Anda gagal atau ditolak. Silakan periksa detail pesanan dan kirim ulang pembayaran jika diperlukan.',
            'data' => [
                'transaction_id' => $transaction->id,
                'order_id' => $order->id,
                'route' => '/student/orders/' . $order->id,
            ],
            'sent_at' => now(),
        ]);
    }

    public function publishEnrollmentActivated(Enrollment $enrollment): void
    {
        $enrollment->loadMissing('courseOffering.course');

        $courseTitle = $enrollment->courseOffering?->course?->title;
        $body = $courseTitle
            ? 'Akses belajar untuk "' . $courseTitle . '" sudah aktif.'
            : 'Akses belajar Anda sudah aktif.';

        $this->saveUniqueNotification([
            'user_id' => $enrollment->user_id,
            'type' => 'enrollment.activated',
            'reference_type' => 'enrollment',
            'reference_id' => $enrollment->id,
        ], [
            'title' => 'Akses kelas aktif',
            'body' => $body,
            'data' => [
                'enrollment_id' => $enrollment->id,
                'order_id' => $enrollment->order_id,
                'course_offering_id' => $enrollment->course_offering_id,
                'course_title' => $courseTitle,
                'route' => '/student/enrollments/' . $enrollment->id,
            ],
            'sent_at' => now(),
        ]);
    }

    public function publishOrderPlaced(Order $order, ?Transaction $transaction = null): void
    {
        $order->loadMissing('user');

        if (! $order->user) {
            return;
        }

        $student = $order->user;
        $adminRecipients = $this->getAdminRecipients();
        $body = $student->fullname . ' membuat order ' . $order->order_code . '.';

        foreach ($adminRecipients as $adminRecipient) {
            $this->saveUniqueNotification([
                'user_id' => $adminRecipient->id,
                'type' => 'order.placed',
                'reference_type' => 'order',
                'reference_id' => $order->id,
            ], [
                'title' => 'Order baru dari student',
                'body' => $body,
                'data' => [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'transaction_id' => $transaction?->id,
                    'student_id' => $student->id,
                    'student_name' => $student->fullname,
                    'route' => '/admin/orders/' . $order->id,
                ],
                'actor_id' => $student->id,
                'read_at' => null,
                'sent_at' => now(),
            ]);
        }
    }

    public function publishAssignmentSubmitted(AssignmentSubmission $submission): void
    {
        $submission->loadMissing([
            'assignment.course:id,title,instructor_id',
            'enrollment.courseOffering:id,course_id,academic_period_id',
            'user:id,fullname',
        ]);

        $assignment = $submission->assignment;
        $course = $assignment?->course;
        $offering = $submission->enrollment?->courseOffering;

        if (! $assignment || ! $course || ! $offering) {
            return;
        }

        $studentName = $submission->user?->fullname ?: 'Student';
        $route = sprintf(
            '/admin/course-activity/assignment-reviews?offeringId=%s&submissionId=%s',
            $offering->id,
            $submission->id
        );

        foreach ($this->getCourseStakeholderRecipients($course, (int) $submission->user_id) as $recipient) {
            $this->saveUniqueNotification([
                'user_id' => $recipient->id,
                'type' => 'assignment.submitted',
                'reference_type' => 'assignment_submission',
                'reference_id' => $submission->id,
            ], [
                'title' => 'Submission assignment baru',
                'body' => $studentName . ' mengirim assignment "' . $assignment->title . '" pada course "' . $course->title . '".',
                'data' => [
                    'course_id' => $course->id,
                    'course_offering_id' => $offering->id,
                    'academic_period_id' => $offering->academic_period_id,
                    'assignment_id' => $assignment->id,
                    'submission_id' => $submission->id,
                    'enrollment_id' => $submission->enrollment_id,
                    'student_id' => $submission->user_id,
                    'route' => $route,
                ],
                'actor_id' => $submission->user_id,
                'read_at' => null,
                'sent_at' => now(),
            ]);
        }
    }

    public function publishForumPostCreated(ForumPost $post): void
    {
        $post->loadMissing(['course:id,title,instructor_id', 'user:id,fullname']);

        $course = $post->course;
        if (! $course) {
            return;
        }

        $studentName = $post->user?->fullname ?: 'Student';

        foreach ($this->getCourseStakeholderRecipients($course, (int) $post->user_id) as $recipient) {
            $this->saveUniqueNotification([
                'user_id' => $recipient->id,
                'type' => 'forum.posted',
                'reference_type' => 'forum_post',
                'reference_id' => $post->id,
            ], [
                'title' => 'Topik forum baru',
                'body' => $studentName . ' membuat topik forum "' . $post->title . '" pada course "' . $course->title . '".',
                'data' => [
                    'course_id' => $course->id,
                    'post_id' => $post->id,
                    'student_id' => $post->user_id,
                    'route' => '/admin/course-activity/forum?courseId=' . $course->id . '&forumPostId=' . $post->id,
                ],
                'actor_id' => $post->user_id,
                'read_at' => null,
                'sent_at' => now(),
            ]);
        }
    }

    public function publishForumReplyCreated(ForumReply $reply): void
    {
        $reply->loadMissing(['post.course:id,title,instructor_id', 'user:id,fullname']);

        $post = $reply->post;
        $course = $post?->course;

        if (! $post || ! $course) {
            return;
        }

        $studentName = $reply->user?->fullname ?: 'Student';

        foreach ($this->getCourseStakeholderRecipients($course, (int) $reply->user_id) as $recipient) {
            $this->saveUniqueNotification([
                'user_id' => $recipient->id,
                'type' => 'forum.replied',
                'reference_type' => 'forum_reply',
                'reference_id' => $reply->id,
            ], [
                'title' => 'Balasan forum baru',
                'body' => $studentName . ' membalas topik "' . $post->title . '" pada course "' . $course->title . '".',
                'data' => [
                    'course_id' => $course->id,
                    'post_id' => $post->id,
                    'reply_id' => $reply->id,
                    'student_id' => $reply->user_id,
                    'route' => '/admin/course-activity/forum?courseId=' . $course->id . '&forumPostId=' . $post->id,
                ],
                'actor_id' => $reply->user_id,
                'read_at' => null,
                'sent_at' => now(),
            ]);
        }
    }

    public function publishForumReplyReceived(ForumReply $reply, bool $skipIfCourseStakeholder = false): void
    {
        $reply->loadMissing([
            'post.course:id,title,instructor_id',
            'post.user:id,fullname,role_id',
            'post.user.role:id,name',
            'user:id,fullname',
        ]);

        $post = $reply->post;
        $course = $post?->course;
        $postOwner = $post?->user;

        if (! $post || ! $course || ! $postOwner || (int) $postOwner->id === (int) $reply->user_id) {
            return;
        }

        if ($skipIfCourseStakeholder && $this->isCourseStakeholderRecipient($postOwner, $course)) {
            return;
        }

        $replierName = $reply->user?->fullname ?: 'Pengguna';
        $route = $this->resolveForumPostOwnerRoute($post, $postOwner);
        $data = [
            'course_id' => $course->id,
            'post_id' => $post->id,
            'reply_id' => $reply->id,
            'post_owner_id' => $postOwner->id,
            'replier_id' => $reply->user_id,
        ];

        if ($route) {
            $data['route'] = $route;
        }

        $this->saveUniqueNotification([
            'user_id' => $postOwner->id,
            'type' => 'forum.reply.received',
            'reference_type' => 'forum_reply',
            'reference_id' => $reply->id,
        ], [
            'title' => 'Topik forum Anda mendapat balasan',
            'body' => $replierName . ' membalas topik "' . $post->title . '" pada course "' . $course->title . '".',
            'data' => $data,
            'actor_id' => $reply->user_id,
            'read_at' => null,
            'sent_at' => now(),
        ]);
    }

    public function publishCourseReviewCreated(Review $review): void
    {
        $review->loadMissing(['course:id,title,instructor_id', 'user:id,fullname']);

        $course = $review->course;
        if (! $course) {
            return;
        }

        $studentName = $review->user?->fullname ?: 'Student';

        foreach ($this->getAdminRecipients((int) $review->user_id) as $recipient) {
            $this->saveUniqueNotification([
                'user_id' => $recipient->id,
                'type' => 'course.review.created',
                'reference_type' => 'review',
                'reference_id' => $review->id,
            ], [
                'title' => 'Review course baru',
                'body' => $studentName . ' memberi rating ' . $review->rating . ' untuk course "' . $course->title . '".',
                'data' => [
                    'course_id' => $course->id,
                    'review_id' => $review->id,
                    'student_id' => $review->user_id,
                    'route' => '/admin/course-reviews?courseId=' . $course->id . '&reviewId=' . $review->id,
                ],
                'actor_id' => $review->user_id,
                'read_at' => null,
                'sent_at' => now(),
            ]);
        }
    }

    private function getAdminRecipients(?int $excludeUserId = null)
    {
        return User::query()
            ->whereHas('role', function ($query) {
                $query->where('name', 'admin');
            })
            ->when($excludeUserId !== null, function ($query) use ($excludeUserId) {
                $query->where('id', '!=', $excludeUserId);
            })
            ->get(['id']);
    }

    private function getCourseStakeholderRecipients(Course $course, ?int $excludeUserId = null)
    {
        return User::query()
            ->where(function ($query) use ($course) {
                $query->whereHas('role', function ($roleQuery) {
                    $roleQuery->where('name', 'admin');
                });

                if ($course->instructor_id) {
                    $query->orWhere('id', $course->instructor_id);
                }
            })
            ->when($excludeUserId !== null, function ($query) use ($excludeUserId) {
                $query->where('id', '!=', $excludeUserId);
            })
            ->get(['id'])
            ->unique('id')
            ->values();
    }

    private function isCourseStakeholderRecipient(User $user, Course $course): bool
    {
        $user->loadMissing('role');

        if ($user->role?->name === 'admin') {
            return true;
        }

        return $user->role?->name === 'instructor' && (int) $course->instructor_id === (int) $user->id;
    }

    private function resolveForumPostOwnerRoute(ForumPost $post, User $postOwner): ?string
    {
        $postOwner->loadMissing('role');

        if (in_array($postOwner->role?->name, ['admin', 'instructor'], true)) {
            return sprintf('/admin/course-activity/forum/%s/%s', $post->course_id, $post->id);
        }

        $enrollmentId = $this->findForumEnrollmentId((int) $post->course_id, (int) $postOwner->id);

        if ($enrollmentId !== null) {
            return sprintf('/student/enrollments/%s/forum/%s', $enrollmentId, $post->id);
        }

        return '/student/notifications';
    }

    private function findForumEnrollmentId(int $courseId, int $userId): ?int
    {
        return Enrollment::query()
            ->where('user_id', $userId)
            ->whereHas('courseOffering', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->whereIn('status', ['active', 'completed'])
            ->latest('id')
            ->value('id');
    }

    private function saveUniqueNotification(array $identity, array $payload): Notification
    {
        $notification = Notification::query()->firstOrNew($identity);

        if ($notification->exists) {
            $notification->fill([
                'title' => $payload['title'],
                'body' => $payload['body'],
                'data' => $payload['data'] ?? null,
                'actor_id' => $payload['actor_id'] ?? $notification->actor_id,
                'read_at' => array_key_exists('read_at', $payload) ? $payload['read_at'] : $notification->read_at,
                'sent_at' => $payload['sent_at'] ?? $notification->sent_at,
            ]);
        } else {
            $notification->fill($identity + $payload);
        }

        $notification->save();

        if ($notification->wasRecentlyCreated) {
            SendPushNotificationJob::dispatch($notification->id)->afterCommit();
        }

        return $notification;
    }
}
