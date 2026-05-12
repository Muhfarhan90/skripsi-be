<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Database\Seeder;

class AssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courseBySlug = Course::query()->pluck('id', 'slug');
        $now = now();

        $rows = [
            [
                'course_slug' => 'introduction-to-programming',
                'title' => 'UAS Pemrograman Dasar',
                'description' => 'Final project untuk menguji pemahaman fundamental pemrograman.',
                'instructions' => 'Buat mini project CLI sederhana dan jelaskan struktur kode.',
                'due_at' => $now->copy()->addDays(30),
                'is_required_for_certificate' => true,
                'allow_resubmission' => true,
                'max_attempts' => 5,
                'status' => 'published',
            ],
            [
                'course_slug' => 'advanced-web-development',
                'title' => 'Project Optional: Landing Page',
                'description' => 'Project opsional untuk pengayaan portofolio.',
                'instructions' => 'Bangun landing page responsif dengan dokumentasi singkat.',
                'due_at' => $now->copy()->addDays(80),
                'is_required_for_certificate' => false,
                'allow_resubmission' => true,
                'max_attempts' => 3,
                'status' => 'published',
            ],
        ];

        foreach ($rows as $row) {
            $courseId = $courseBySlug->get($row['course_slug']);
            if (! $courseId) {
                continue;
            }

            Assignment::updateOrCreate(
                [
                    'course_id' => $courseId,
                    'title' => $row['title'],
                ],
                [
                    'course_id' => $courseId,
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'instructions' => $row['instructions'],
                    'due_at' => $row['due_at'],
                    'is_required_for_certificate' => $row['is_required_for_certificate'],
                    'allow_resubmission' => $row['allow_resubmission'],
                    'max_attempts' => $row['max_attempts'],
                    'status' => $row['status'],
                ]
            );
        }
    }
}
