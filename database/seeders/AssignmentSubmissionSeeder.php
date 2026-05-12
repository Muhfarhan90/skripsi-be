<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
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
        $instructorId = User::where('email', 'instructor@example.com')->value('id');

        $activeEnrollment = $this->findEnrollmentByStudentAndOffering(
            (int) $students->get('student@example.com'),
            'Intro Programming - Cohort A1 2026'
        );
        $completedEnrollment = $this->findEnrollmentByStudentAndOffering(
            (int) $students->get('student.completed@example.com'),
            'Intro Programming - Cohort Legacy 2025'
        );

        $activeAssignment = $this->findAssignmentByCourseAndTitle(
            'introduction-to-programming',
            'UAS Pemrograman Dasar'
        );
        $completedAssignment = $this->findAssignmentByCourseAndTitle(
            'introduction-to-programming',
            'UAS Pemrograman Dasar'
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
                    'submission_text' => 'Draft project CLI untuk validasi awal.',
                    'attachment_url' => 'https://example.com/submissions/uas-active-v1.zip',
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
                    'submission_text' => 'Final project lengkap sesuai rubric.',
                    'attachment_url' => 'https://example.com/submissions/uas-completed-v1.zip',
                    'status' => 'approved',
                    'review_notes' => 'Project approved.',
                    'reviewed_by' => $instructorId,
                    'submitted_at' => now()->subDays(110),
                    'reviewed_at' => now()->subDays(108),
                ]
            );
        }
    }

    private function findEnrollmentByStudentAndOffering(int $studentId, string $offeringTitle): ?Enrollment
    {
        if (! $studentId) {
            return null;
        }

        return Enrollment::query()
            ->where('user_id', $studentId)
            ->whereHas('courseOffering', function ($query) use ($offeringTitle) {
                $query->where('title', $offeringTitle);
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
