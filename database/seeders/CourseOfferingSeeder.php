<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\CourseOffering;
use Illuminate\Database\Seeder;

class CourseOfferingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $courseBySlug = Course::query()->pluck('id', 'slug');
        $periodByCode = AcademicPeriod::query()->pluck('id', 'code');

        $offerings = [
            [
                'title' => 'Intro Programming - Cohort A1 2026',
                'course_slug' => 'introduction-to-programming',
                'period_code' => 'PRE-U-2026-A',
                'start_at' => $now->copy()->subDays(20),
                'end_at' => $now->copy()->addDays(50),
                'enrollment_open_at' => $now->copy()->subDays(45),
                'enrollment_close_at' => $now->copy()->addDays(10),
                'capacity' => 120,
                'price' => 500000,
                'discount_price' => 450000,
                'status' => 'published',
            ],
            [
                'title' => 'Advanced Web Dev - Cohort B1 2026',
                'course_slug' => 'advanced-web-development',
                'period_code' => 'PRE-U-2026-A',
                'start_at' => $now->copy()->addDays(20),
                'end_at' => $now->copy()->addDays(120),
                'enrollment_open_at' => $now->copy()->subDays(10),
                'enrollment_close_at' => $now->copy()->addDays(30),
                'capacity' => 80,
                'price' => 750000,
                'discount_price' => 700000,
                'status' => 'published',
            ],
            [
                'title' => 'Health Wellness - Cohort Legacy 2025',
                'course_slug' => 'health-and-wellness',
                'period_code' => 'PRE-U-2025-B',
                'start_at' => $now->copy()->subDays(160),
                'end_at' => $now->copy()->subDays(70),
                'enrollment_open_at' => $now->copy()->subDays(220),
                'enrollment_close_at' => $now->copy()->subDays(170),
                'capacity' => 60,
                'price' => 350000,
                'discount_price' => 300000,
                'status' => 'published',
            ],
            [
                'title' => 'Intro Programming - Cohort Legacy 2025',
                'course_slug' => 'introduction-to-programming',
                'period_code' => 'PRE-U-2025-B',
                'start_at' => $now->copy()->subDays(180),
                'end_at' => $now->copy()->subDays(90),
                'enrollment_open_at' => $now->copy()->subDays(240),
                'enrollment_close_at' => $now->copy()->subDays(190),
                'capacity' => 100,
                'price' => 500000,
                'discount_price' => 475000,
                'status' => 'published',
            ],
        ];

        foreach ($offerings as $offering) {
            $courseId = $courseBySlug->get($offering['course_slug']);
            $periodId = $periodByCode->get($offering['period_code']);

            if (! $courseId) {
                continue;
            }

            CourseOffering::updateOrCreate(
                ['title' => $offering['title']],
                [
                    'course_id' => $courseId,
                    'academic_period_id' => $periodId,
                    'title' => $offering['title'],
                    'start_at' => $offering['start_at'],
                    'end_at' => $offering['end_at'],
                    'enrollment_open_at' => $offering['enrollment_open_at'],
                    'enrollment_close_at' => $offering['enrollment_close_at'],
                    'capacity' => $offering['capacity'],
                    'price' => $offering['price'],
                    'discount_price' => $offering['discount_price'],
                    'status' => $offering['status'],
                ]
            );
        }
    }
}

