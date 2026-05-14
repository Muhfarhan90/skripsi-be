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
        $courseBySlug = Course::query()->pluck('id', 'slug');
        $periodByCode = AcademicPeriod::query()->pluck('id', 'code');

        $offerings = [
            [
                'title' => 'Intro Programming - Cohort A1 2026',
                'course_slug' => 'introduction-to-programming',
                'period_code' => 'PRE-U-2026-A',
                'capacity' => 120,
                'price' => 500000,
                'discount_price' => 450000,
                'is_active' => true,
            ],
            [
                'title' => 'Advanced Web Dev - Cohort B1 2026',
                'course_slug' => 'advanced-web-development',
                'period_code' => 'PRE-U-2026-A',
                'capacity' => 80,
                'price' => 750000,
                'discount_price' => 700000,
                'is_active' => true,
            ],
            [
                'title' => 'Health Wellness - Cohort Legacy 2025',
                'course_slug' => 'health-and-wellness',
                'period_code' => 'PRE-U-2025-B',
                'capacity' => 60,
                'price' => 350000,
                'discount_price' => 300000,
                'is_active' => true,
            ],
            [
                'title' => 'Intro Programming - Cohort Legacy 2025',
                'course_slug' => 'introduction-to-programming',
                'period_code' => 'PRE-U-2025-B',
                'capacity' => 100,
                'price' => 500000,
                'discount_price' => 475000,
                'is_active' => true,
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
                    'capacity' => $offering['capacity'],
                    'price' => $offering['price'],
                    'discount_price' => $offering['discount_price'],
                    'is_active' => $offering['is_active'],
                ]
            );
        }
    }
}
