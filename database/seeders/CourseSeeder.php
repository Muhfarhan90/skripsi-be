<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courses = [
            [
                'title' => 'Introduction to Programming',
                'slug' => 'introduction-to-programming',
                'description' => 'Learn the basics of programming with this introductory course.',
                'category_id' => 1,
                'instructor_id' => 1,
                'price' => 49.99,
                'discount_price' => 29.99,
                'status' => 'published',
                'requirements' => 'No prior programming experience required.',
                'outcomes' => 'Understand programming fundamentals and write basic code.',
            ],
            [
                'title' => 'Advanced Web Development',
                'slug' => 'advanced-web-development',
                'description' => 'Take your web development skills to the next level with this advanced course.',
                'category_id' => 1,
                'instructor_id' => 1,
                'price' => 99.99,
                'discount_price' => 79.99,
                'status' => 'published',
                'requirements' => 'Basic knowledge of HTML, CSS, and JavaScript.',
                'outcomes' => 'Build complex web applications using modern frameworks.',
            ],
            [
                'title' => 'Health and Wellness',
                'slug' => 'health-and-wellness',
                'description' => 'Discover tips and strategies for maintaining a healthy lifestyle.',
                'category_id' => 2,
                'instructor_id' => 2,
                'price' => 39.99,
                'discount_price' => 19.99,
                'status' => 'published',
                'requirements' => 'None',
                'outcomes' => 'Learn how to improve your health and wellness.',
            ],
        ];

        foreach ($courses as $course) {
            Course::updateOrCreate(['slug' => $course['slug']], $course);
        }
    }
}
