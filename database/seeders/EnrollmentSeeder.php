<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $enrollments = [
            [
                'user_id' => 1,
                'course_id' => 1,
                'transaction_id' => null,
                'last_lesson_id' => null,
                'progress' => 0,
                'status' => 'active',
                'completed_at' => null,
                'expired_at' => null,
            ],
            [
                'user_id' => 2,
                'course_id' => 1,
                'transaction_id' => null,
                'last_lesson_id' => null,
                'progress' => 0,
                'status' => 'active',
                'completed_at' => null,
                'expired_at' => null,
            ],
            [
                'user_id' => 1,
                'course_id' => 2,
                'transaction_id' => null,
                'last_lesson_id' => null,
                'progress' => 0,
                'status' => 'active',
                'completed_at' => null,
                'expired_at' => null,
            ],
        ];

        foreach ($enrollments as $enrollment) {
            Enrollment::updateOrCreate(
                ['user_id' => $enrollment['user_id'], 'course_id' => $enrollment['course_id']],
                $enrollment
            );
        }
    }
}
