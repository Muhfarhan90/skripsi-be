<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $skills = [
            [
                'name' => 'Java',
                'slug' => 'java',
                'is_active' => true,
            ],
            [
                'name' => 'Programming Fundamentals',
                'slug' => 'programming-fundamentals',
                'is_active' => true,
            ],
            [
                'name' => 'Problem Solving',
                'slug' => 'problem-solving',
                'is_active' => true,
            ],
            [
                'name' => 'HTML & CSS',
                'slug' => 'html-css',
                'is_active' => true,
            ],
            [
                'name' => 'JavaScript',
                'slug' => 'javascript',
                'is_active' => true,
            ],
            [
                'name' => 'Web Development',
                'slug' => 'web-development',
                'is_active' => true,
            ],
            [
                'name' => 'Healthy Lifestyle',
                'slug' => 'healthy-lifestyle',
                'is_active' => true,
            ],
            [
                'name' => 'Wellness',
                'slug' => 'wellness',
                'is_active' => true,
            ],
        ];

        foreach ($skills as $skill) {
            DB::table('skills')->updateOrInsert(
                ['slug' => $skill['slug']],
                [
                    'name' => $skill['name'],
                    'is_active' => $skill['is_active'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $skillBySlug = DB::table('skills')->pluck('id', 'slug');
        $courseBySlug = Course::query()->pluck('id', 'slug');

        $courseSkillMap = [
            'introduction-to-programming' => ['java', 'programming-fundamentals', 'problem-solving'],
            'advanced-web-development' => ['html-css', 'javascript', 'web-development'],
            'health-and-wellness' => ['healthy-lifestyle', 'wellness'],
        ];

        foreach ($courseSkillMap as $courseSlug => $skillSlugs) {
            $courseId = $courseBySlug->get($courseSlug);
            if (! $courseId) {
                continue;
            }

            foreach (array_values($skillSlugs) as $index => $skillSlug) {
                $skillId = $skillBySlug->get($skillSlug);
                if (! $skillId) {
                    continue;
                }

                DB::table('course_skills')->updateOrInsert(
                    [
                        'course_id' => $courseId,
                        'skill_id' => $skillId,
                    ],
                    [
                        'sort_order' => $index,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
