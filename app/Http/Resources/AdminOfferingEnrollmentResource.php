<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOfferingEnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;
        $certificate = $this->relationLoaded('certificate') ? $this->certificate : null;
        $assignmentRequirement = $this->getAttribute('assignment_requirement');
        $hasCertificate = $certificate !== null;
        $isAssignmentSatisfied = is_array($assignmentRequirement)
            ? (bool) ($assignmentRequirement['is_satisfied'] ?? false)
            : false;
        $canGenerateCertificate = $hasCertificate
            || (
                (int) ($this->progress ?? 0) >= 100
                && (string) ($this->status ?? '') === 'completed'
                && $isAssignmentSatisfied
            );
        $certificateBlockReason = null;

        if (! $canGenerateCertificate) {
            if ((int) ($this->progress ?? 0) < 100) {
                $certificateBlockReason = 'Progress belum 100%.';
            } elseif ((string) ($this->status ?? '') !== 'completed') {
                $certificateBlockReason = 'Enrollment belum completed.';
            } elseif (! $isAssignmentSatisfied) {
                $certificateBlockReason = 'Assignment wajib belum terpenuhi.';
            } else {
                $certificateBlockReason = 'Sertifikat belum memenuhi syarat generate.';
            }
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'course_offering_id' => $this->course_offering_id,
            'order_id' => $this->order_id,
            'last_lesson_id' => $this->last_lesson_id,
            'progress' => $this->progress,
            'status' => $this->status,
            'user' => $user ? [
                'id' => $user->id,
                'fullname' => $user->fullname,
                'email' => $user->email,
            ] : null,
            'assignment_requirement' => is_array($assignmentRequirement)
                ? [
                    'required_assignments' => (int) ($assignmentRequirement['required_assignments'] ?? 0),
                    'approved_assignments' => (int) ($assignmentRequirement['approved_assignments'] ?? 0),
                    'is_satisfied' => (bool) ($assignmentRequirement['is_satisfied'] ?? false),
                ]
                : null,
            'has_certificate' => $hasCertificate,
            'certificate_status' => $hasCertificate ? 'Active' : 'Belum Ada',
            'can_generate_certificate' => $canGenerateCertificate,
            'certificate_block_reason' => $certificateBlockReason,
            'certificate' => $certificate ? [
                'id' => $certificate->id,
                'certificate_number' => $certificate->certificate_number,
                'certificate_url' => $certificate->certificate_url,
                'status' => $certificate->status,
                'template_version' => $certificate->template_version,
                'verification_code' => $certificate->verification_code,
                'issued_at' => $certificate->issued_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'expired_at' => $certificate->expired_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'revoked_at' => $certificate->revoked_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'revoked_reason' => $certificate->revoked_reason,
                'created_at' => $certificate->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'updated_at' => $certificate->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            ] : null,
            'started_at' => $this->started_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'ended_at' => $this->ended_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'completed_at' => $this->completed_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'expired_at' => ($this->ended_at ?? $this->expired_at)?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
