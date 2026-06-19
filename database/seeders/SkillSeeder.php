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
                'name' => 'Anamnesis Dasar',
                'slug' => 'anamnesis-dasar',
                'is_active' => true,
            ],
            [
                'name' => 'Keselamatan Pasien',
                'slug' => 'keselamatan-pasien',
                'is_active' => true,
            ],
            [
                'name' => 'Studi Kasus Klinis',
                'slug' => 'studi-kasus-klinis',
                'is_active' => true,
            ],
            [
                'name' => 'Budidaya Tanaman',
                'slug' => 'budidaya-tanaman',
                'is_active' => true,
            ],
            [
                'name' => 'Teknologi Pertanian',
                'slug' => 'teknologi-pertanian',
                'is_active' => true,
            ],
            [
                'name' => 'Perencanaan Panen',
                'slug' => 'perencanaan-panen',
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
            'pemrograman-web' => ['html-css', 'javascript', 'web-development'],
            'dasar-kedokteran-klinis' => ['anamnesis-dasar', 'keselamatan-pasien', 'studi-kasus-klinis'],
            'teknologi-pertanian-modern' => ['budidaya-tanaman', 'teknologi-pertanian', 'perencanaan-panen'],
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
