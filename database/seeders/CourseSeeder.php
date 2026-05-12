<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $technologyCategoryId = Category::where('slug', 'technology')->value('id');
        $healthCategoryId = Category::where('slug', 'health')->value('id');
        $adminInstructorId = User::where('email', 'admin@example.com')->value('id');
        $instructorId = User::where('email', 'instructor@example.com')->value('id');

        $courses = [
            [
                'title' => 'Introduction to Programming',
                'slug' => 'introduction-to-programming',
                'description' => 'Learn the basics of programming with this introductory course.',
                'category_id' => $technologyCategoryId,
                'instructor_id' => $adminInstructorId,
                'requirements' => 'No prior programming experience required.',
                'outcomes' => 'Understand programming fundamentals and write basic code.',
            ],
            [
                'title' => 'Advanced Web Development',
                'slug' => 'advanced-web-development',
                'description' => 'Take your web development skills to the next level with this advanced course.',
                'category_id' => $technologyCategoryId,
                'instructor_id' => $adminInstructorId,
                'requirements' => 'Basic knowledge of HTML, CSS, and JavaScript.',
                'outcomes' => 'Build complex web applications using modern frameworks.',
            ],
            [
                'title' => 'Health and Wellness',
                'slug' => 'health-and-wellness',
                'description' => 'Discover tips and strategies for maintaining a healthy lifestyle.',
                'category_id' => $healthCategoryId,
                'instructor_id' => $instructorId,
                'requirements' => 'None',
                'outcomes' => 'Learn how to improve your health and wellness.',
            ],
        ];

        foreach ($courses as $course) {
            Course::updateOrCreate(['slug' => $course['slug']], $course);
        }
    }
}
