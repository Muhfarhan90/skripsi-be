<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssignmentSubmissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = User::query()->pluck('id', 'email');
        $instructorId = Course::query()
            ->where('slug', 'pemrograman-web')
            ->value('instructor_id')
            ?? User::where('email', 'instructor@example.com')->value('id');

        $activeEnrollment = $this->findEnrollmentByStudentAndOffering(
            (int) $students->get('student@example.com'),
            'pemrograman-web',
            'PRE-U-2026-A'
        );
        $completedEnrollment = $this->findEnrollmentByStudentAndOffering(
            (int) $students->get('student.completed@example.com'),
            'pemrograman-web',
            'PRE-U-2025-B'
        );

        $activeAssignment = $this->findAssignmentByCourseAndTitle(
            'pemrograman-web',
            'Tugas Project Pemrograman Web'
        );
        $completedAssignment = $this->findAssignmentByCourseAndTitle(
            'pemrograman-web',
            'Tugas Project Pemrograman Web'
        );

        if ($activeEnrollment && $activeAssignment) {
            AssignmentSubmission::updateOrCreate(
                [
                    'assignment_id' => $activeAssignment->id,
                    'enrollment_id' => $activeEnrollment->id,
                    'attempt_no' => 1,
                ],
                [
                    'user_id' => $activeEnrollment->user_id,
                    'submission_text' => 'Draft halaman web responsif untuk validasi awal.',
                    'attachment_url' => 'https://example.com/submissions/web-project-active-v1.zip',
                    'status' => 'submitted',
                    'review_notes' => null,
                    'reviewed_by' => null,
                    'submitted_at' => now()->subDays(1),
                    'reviewed_at' => null,
                ]
            );
        }

        if ($completedEnrollment && $completedAssignment) {
            AssignmentSubmission::updateOrCreate(
                [
                    'assignment_id' => $completedAssignment->id,
                    'enrollment_id' => $completedEnrollment->id,
                    'attempt_no' => 1,
                ],
                [
                    'user_id' => $completedEnrollment->user_id,
                    'submission_text' => 'Final project halaman web lengkap sesuai rubric.',
                    'attachment_url' => 'https://example.com/submissions/web-project-completed-v1.zip',
                    'status' => 'approved',
                    'review_notes' => 'Project approved.',
                    'reviewed_by' => $instructorId,
                    'submitted_at' => now()->subDays(110),
                    'reviewed_at' => now()->subDays(108),
                ]
            );
        }
    }

    private function findEnrollmentByStudentAndOffering(
        int $studentId,
        string $courseSlug,
        string $periodCode
    ): ?Enrollment
    {
        if (! $studentId) {
            return null;
        }

        return Enrollment::query()
            ->where('user_id', $studentId)
            ->whereHas('courseOffering', function ($query) use ($courseSlug, $periodCode) {
                $query
                    ->whereHas('course', function ($courseQuery) use ($courseSlug) {
                        $courseQuery->where('slug', $courseSlug);
                    })
                    ->whereHas('academicPeriod', function ($periodQuery) use ($periodCode) {
                        $periodQuery->where('code', $periodCode);
                    });
            })
            ->first();
    }

    private function findAssignmentByCourseAndTitle(string $courseSlug, string $assignmentTitle): ?Assignment
    {
        return Assignment::query()
            ->where('title', $assignmentTitle)
            ->whereHas('course', function ($query) use ($courseSlug) {
                $query->where('slug', $courseSlug);
            })
            ->first();
    }
}
