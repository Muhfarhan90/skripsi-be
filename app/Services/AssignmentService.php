<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function getAssignmentsForEnrollment(int $userId, int $enrollmentId): array
    {
        $enrollment = Enrollment::with('courseOffering')->where('user_id', $userId)->findOrFail($enrollmentId);
        $courseId = $this->resolveCourseId($enrollment);

        $assignments = Assignment::query()
            ->where('course_id', $courseId)
            ->where('status', 'published')
            ->with([
                'submissions' => function ($query) use ($enrollment) {
                    $query->where('enrollment_id', $enrollment->id)
                        ->with('reviewer:id,fullname');
                },
            ])
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();

        $assignments->each(function (Assignment $assignment): void {
            $assignment->setRelation('latestSubmission', $assignment->submissions->sortByDesc('attempt_no')->first());
            $assignment->unsetRelation('submissions');
        });

        return [
            'enrollment' => $enrollment,
            'assignments' => $assignments,
            'completion_requirement' => $this->getCompletionRequirementSummary($enrollment),
        ];
    }

    public function getAssignmentDetailForEnrollment(int $userId, int $enrollmentId, int $assignmentId): array
    {
        $enrollment = Enrollment::with('courseOffering')->where('user_id', $userId)->findOrFail($enrollmentId);
        $courseId = $this->resolveCourseId($enrollment);

        $assignment = Assignment::query()
            ->where('id', $assignmentId)
            ->where('course_id', $courseId)
            ->where('status', 'published')
            ->with([
                'submissions' => function ($query) use ($enrollment) {
                    $query->where('enrollment_id', $enrollment->id)
                        ->with('reviewer:id,fullname')
                        ->orderByDesc('attempt_no');
                },
            ])
            ->firstOrFail();

        return [
            'enrollment' => $enrollment,
            'assignment' => $assignment,
            'completion_requirement' => $this->getCompletionRequirementSummary($enrollment),
        ];
    }

    public function submitForEnrollment(int $userId, int $enrollmentId, int $assignmentId, array $data): AssignmentSubmission
    {
        $enrollment = Enrollment::with('courseOffering')->where('user_id', $userId)->findOrFail($enrollmentId);
        $courseId = $this->resolveCourseId($enrollment);

        $assignment = Assignment::query()
            ->where('id', $assignmentId)
            ->where('course_id', $courseId)
            ->where('status', 'published')
            ->firstOrFail();

        $this->assertStudentCanSubmit($enrollment, $assignment);

        $submissionText = $data['submission_text'] ?? null;
        $attachmentUrl = $data['attachment_url'] ?? null;
        if (($submissionText === null || trim((string) $submissionText) === '') && ($attachmentUrl === null || trim((string) $attachmentUrl) === '')) {
            throw ValidationException::withMessages([
                'submission' => ['Please provide submission_text or attachment_url.'],
            ]);
        }

        $latest = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('enrollment_id', $enrollment->id)
            ->orderByDesc('attempt_no')
            ->first();

        if ($latest) {
            if ($latest->status === 'submitted') {
                throw ValidationException::withMessages([
                    'submission' => ['Previous submission is still waiting for instructor review.'],
                ]);
            }

            if ($latest->status === 'approved') {
                throw ValidationException::withMessages([
                    'submission' => ['Assignment is already approved for this enrollment.'],
                ]);
            }

            if ($latest->status === 'revision_required' && ! (bool) $assignment->allow_resubmission) {
                throw ValidationException::withMessages([
                    'submission' => ['Resubmission is not allowed for this assignment.'],
                ]);
            }

            if ($assignment->max_attempts !== null && $latest->attempt_no !== null && $latest->attempt_no >= $assignment->max_attempts) {
                throw ValidationException::withMessages([
                    'submission' => ['Maximum submission attempts reached.'],
                ]);
            }
        }

        $attemptNo = ($latest?->attempt_no ?? 0) + 1;

        return AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'attempt_no' => $attemptNo,
            'submission_text' => $submissionText,
            'attachment_url' => $attachmentUrl,
            'status' => 'submitted',
            'review_notes' => null,
            'reviewed_by' => null,
            'submitted_at' => now(),
            'reviewed_at' => null,
        ])->load('reviewer:id,fullname');
    }

    public function getAssignmentsByCourseForAdmin(int $courseId, User $actor)
    {
        $course = Course::query()->findOrFail($courseId);
        $this->assertCanManageCourse($course, $actor);

        return Assignment::query()
            ->where('course_id', $course->id)
            ->with(['creator:id,fullname', 'section:id,course_id,title'])
            ->latest()
            ->paginate(15);
    }

    public function createForCourse(int $courseId, User $actor, array $data): Assignment
    {
        $course = Course::query()->findOrFail($courseId);
        $this->assertCanManageCourse($course, $actor);
        $sectionId = $this->resolveSectionIdForCourse($course, $data['section_id'] ?? null);

        $assignment = Assignment::create([
            'course_id' => $course->id,
            'section_id' => $sectionId,
            'created_by' => $actor->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'is_required_for_certificate' => $data['is_required_for_certificate'] ?? true,
            'allow_resubmission' => $data['allow_resubmission'] ?? true,
            'max_attempts' => $data['max_attempts'] ?? null,
            'status' => $data['status'] ?? 'published',
        ]);

        return $assignment->load(['creator:id,fullname', 'section:id,course_id,title']);
    }

    public function updateForCourse(int $courseId, int $assignmentId, User $actor, array $data): Assignment
    {
        $course = Course::query()->findOrFail($courseId);
        $this->assertCanManageCourse($course, $actor);
        $sectionId = $this->resolveSectionIdForCourse($course, $data['section_id'] ?? null);

        $assignment = Assignment::query()
            ->where('id', $assignmentId)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $data['section_id'] = $sectionId;
        $assignment->update($data);

        return $assignment->fresh(['creator:id,fullname', 'section:id,course_id,title']);
    }

    public function getSubmissionsForAssignmentForAdmin(int $assignmentId, User $actor)
    {
        $assignment = Assignment::with('course')->findOrFail($assignmentId);
        $course = $assignment->course;

        if (! $course) {
            throw ValidationException::withMessages([
                'course_id' => ['Assignment is missing a valid course reference.'],
            ]);
        }

        $this->assertCanManageCourse($course, $actor);

        return AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->with([
                'user:id,fullname,email',
                'reviewer:id,fullname',
                'enrollment:id,user_id,course_offering_id,status,progress',
            ])
            ->orderByDesc('attempt_no')
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function reviewSubmissionForAdmin(int $submissionId, User $actor, array $data): AssignmentSubmission
    {
        $submission = AssignmentSubmission::with('assignment.course', 'enrollment')
            ->findOrFail($submissionId);

        $assignment = $submission->assignment;
        if (! $assignment || ! $assignment->course) {
            throw ValidationException::withMessages([
                'assignment_id' => ['Submission is missing a valid assignment course reference.'],
            ]);
        }

        $this->assertCanManageCourse($assignment->course, $actor);

        if ($submission->status === 'approved') {
            throw ValidationException::withMessages([
                'status' => ['Approved submission cannot be reviewed again.'],
            ]);
        }

        $submission->update([
            'status' => $data['status'],
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        return $submission->fresh(['user:id,fullname,email', 'reviewer:id,fullname', 'assignment']);
    }

    public function isCompletionRequirementMet(Enrollment $enrollment): bool
    {
        $summary = $this->getCompletionRequirementSummary($enrollment);

        return (bool) $summary['is_satisfied'];
    }

    public function getCompletionRequirementSummary(Enrollment $enrollment): array
    {
        $requiredAssignmentIds = $this->getRequiredAssignmentIdsForEnrollment($enrollment);
        $requiredCount = count($requiredAssignmentIds);

        if ($requiredCount === 0) {
            return [
                'required_assignments' => 0,
                'approved_assignments' => 0,
                'is_satisfied' => true,
            ];
        }

        $approvedCount = AssignmentSubmission::query()
            ->whereIn('assignment_id', $requiredAssignmentIds)
            ->where('enrollment_id', $enrollment->id)
            ->where('status', 'approved')
            ->distinct('assignment_id')
            ->count('assignment_id');

        return [
            'required_assignments' => $requiredCount,
            'approved_assignments' => $approvedCount,
            'is_satisfied' => $approvedCount >= $requiredCount,
        ];
    }

    private function getRequiredAssignmentIdsForEnrollment(Enrollment $enrollment): array
    {
        $courseId = $this->resolveCourseId($enrollment);

        return Assignment::query()
            ->where('course_id', $courseId)
            ->where('status', 'published')
            ->where('is_required_for_certificate', true)
            ->pluck('id')
            ->all();
    }

    private function resolveCourseId(Enrollment $enrollment): int
    {
        $enrollment->loadMissing('courseOffering');
        $offering = $enrollment->courseOffering;

        if (! $offering || ! $offering->course_id) {
            throw ValidationException::withMessages([
                'course_offering_id' => ['Enrollment is missing a valid course offering/course reference.'],
            ]);
        }

        return (int) $offering->course_id;
    }

    private function assertStudentCanSubmit(Enrollment $enrollment, Assignment $assignment): void
    {
        if (! in_array((string) $enrollment->status, ['active', 'completed'], true)) {
            throw ValidationException::withMessages([
                'enrollment_id' => ['Assignment submission is only allowed for active/completed enrollments.'],
            ]);
        }

        $now = now();
        if ($enrollment->started_at && $now->lt($enrollment->started_at)) {
            throw ValidationException::withMessages([
                'enrollment_id' => ['Enrollment has not started yet.'],
            ]);
        }

        $endedAt = $this->resolveEnrollmentEndAt($enrollment);
        if ($endedAt && $now->gt($endedAt)) {
            throw ValidationException::withMessages([
                'enrollment_id' => ['Assignment submission is not allowed after enrollment period ends.'],
            ]);
        }

        if ($assignment->status !== 'published') {
            throw ValidationException::withMessages([
                'assignment_id' => ['Assignment is not available for submission.'],
            ]);
        }
    }

    private function resolveEnrollmentEndAt(Enrollment $enrollment): ?Carbon
    {
        if ($enrollment->ended_at) {
            return $enrollment->ended_at;
        }

        return $enrollment->expired_at;
    }

    private function assertCanManageCourse(Course $course, User $actor): void
    {
        $actor->loadMissing('role');
        $roleName = $actor->role?->name;

        if ($roleName === 'admin') {
            return;
        }

        if ($roleName === 'instructor' && (int) $course->instructor_id === (int) $actor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'role' => ['You do not have permission to manage assignments for this course.'],
        ]);
    }

    private function resolveSectionIdForCourse(Course $course, mixed $sectionId): ?int
    {
        if ($sectionId === null || $sectionId === '') {
            return null;
        }

        $resolvedSection = Section::query()
            ->where('id', (int) $sectionId)
            ->where('course_id', $course->id)
            ->first();

        if (! $resolvedSection) {
            throw ValidationException::withMessages([
                'section_id' => ['Section does not belong to this course.'],
            ]);
        }

        return (int) $resolvedSection->id;
    }
}
