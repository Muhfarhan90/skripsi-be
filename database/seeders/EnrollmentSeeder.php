<?php

namespace Database\Seeders;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class EnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $studentIds = User::query()->pluck('id', 'email');
        $offeringByCourseAndPeriod = CourseOffering::query()
            ->with(['course:id,slug', 'academicPeriod:id,code'])
            ->get()
            ->keyBy(fn (CourseOffering $offering) => sprintf(
                '%s|%s',
                (string) $offering->course?->slug,
                (string) $offering->academicPeriod?->code
            ));
        $orderByCode = Order::query()->pluck('id', 'order_code');

        $enrollments = [
            [
                'student_email' => 'student@example.com',
                'course_slug' => 'pemrograman-web',
                'period_code' => 'PRE-U-2026-A',
                'order_code' => 'ORD-20260509-ACTIVE',
                'status' => 'active',
                'progress' => 0,
                'started_at' => now()->subDays(20),
                'ended_at' => now()->addDays(50),
                'completed_at' => null,
            ],
            [
                'student_email' => 'student.waiting@example.com',
                'course_slug' => 'dasar-kedokteran-klinis',
                'period_code' => 'PRE-U-2026-A',
                'order_code' => 'ORD-20260509-WAITING',
                'status' => 'pending',
                'progress' => 0,
                'started_at' => now()->addDays(20),
                'ended_at' => now()->addDays(120),
                'completed_at' => null,
            ],
            [
                'student_email' => 'student.expired@example.com',
                'course_slug' => 'teknologi-pertanian-modern',
                'period_code' => 'PRE-U-2025-B',
                'order_code' => 'ORD-20260509-EXPIRED',
                'status' => 'expired',
                'progress' => 50,
                'started_at' => now()->subDays(160),
                'ended_at' => now()->subDays(70),
                'completed_at' => null,
            ],
            [
                'student_email' => 'student.completed@example.com',
                'course_slug' => 'pemrograman-web',
                'period_code' => 'PRE-U-2025-B',
                'order_code' => 'ORD-20260509-COMPLETE',
                'status' => 'completed',
                'progress' => 100,
                'started_at' => now()->subDays(180),
                'ended_at' => now()->subDays(90),
                'completed_at' => now()->subDays(95),
            ],
        ];

        foreach ($enrollments as $seed) {
            $userId = $studentIds->get($seed['student_email']);
            $offering = $offeringByCourseAndPeriod->get(sprintf(
                '%s|%s',
                $seed['course_slug'],
                $seed['period_code']
            ));
            $orderId = $orderByCode->get($seed['order_code']);

            if (! $userId || ! $offering) {
                continue;
            }

            Enrollment::updateOrCreate(
                [
                    'user_id' => $userId,
                    'course_offering_id' => $offering->id,
                ],
                [
                    'course_offering_id' => $offering->id,
                    'order_id' => $orderId,
                    'progress' => $seed['progress'],
                    'status' => $seed['status'],
                    'started_at' => $seed['started_at'],
                    'ended_at' => $seed['ended_at'],
                    'expired_at' => $seed['ended_at'],
                    'completed_at' => $seed['completed_at'],
                ]
            );
        }
    }
}
