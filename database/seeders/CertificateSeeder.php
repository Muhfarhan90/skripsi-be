<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

class CertificateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $studentId = User::where('email', 'student.completed@example.com')->value('id');
        if (! $studentId) {
            return;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $studentId)
            ->where('status', 'completed')
            ->latest('id')
            ->first();

        if (! $enrollment) {
            return;
        }
        $enrollment->loadMissing('courseOffering');
        $courseId = $enrollment->courseOffering?->course_id;
        if (! $courseId) {
            return;
        }

        $requiredAssignmentIds = Assignment::query()
            ->where('course_id', $courseId)
            ->where('status', 'published')
            ->where('is_required_for_certificate', true)
            ->pluck('id')
            ->all();

        if (count($requiredAssignmentIds) > 0) {
            $approvedCount = AssignmentSubmission::query()
                ->where('enrollment_id', $enrollment->id)
                ->whereIn('assignment_id', $requiredAssignmentIds)
                ->where('status', 'approved')
                ->distinct('assignment_id')
                ->count('assignment_id');

            if ($approvedCount < count($requiredAssignmentIds)) {
                return;
            }
        }

        $certificateNumber = 'CERT-SEED-' . str_pad((string) $enrollment->id, 6, '0', STR_PAD_LEFT);

        Certificate::updateOrCreate(
            ['enrollment_id' => $enrollment->id],
            [
                'user_id' => $enrollment->user_id,
                'course_id' => $courseId,
                'certificate_number' => $certificateNumber,
                'certificate_url' => url('/certificates/' . $certificateNumber . '.pdf'),
                'issued_at' => $enrollment->completed_at ?? now()->subDays(90),
                'expired_at' => null,
            ]
        );
    }
}
