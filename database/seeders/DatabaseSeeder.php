<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            CourseSeeder::class,
            SkillSeeder::class,
            SectionSeeder::class,
            LessonSeeder::class,
            QuizSeeder::class,
            QuestionSeeder::class,
            OptionSeeder::class,
            VoucherSeeder::class,
            AcademicPeriodSeeder::class,
            CourseOfferingSeeder::class,
            AssignmentSeeder::class,
            OrderSeeder::class,
            EnrollmentSeeder::class,
            AssignmentSubmissionSeeder::class,
            CertificateSeeder::class,
            LessonProgressSeeder::class,
            QuizAttemptSeeder::class,
            QuizAnswerSeeder::class,
        ]);
    }
}
