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
        $categoryBySlug = Category::query()->pluck('id', 'slug');
        $instructorByEmail = User::query()->pluck('id', 'email');

        $courses = [
            [
                'title' => 'Pemrograman Web',
                'slug' => 'pemrograman-web',
                'description' => 'Belajar fondasi pengembangan website mulai dari HTML, CSS, JavaScript, hingga konsep backend sederhana.',
                'category_slug' => 'ilmu-komputer',
                'instructor_email' => 'web.instructor@example.com',
                'requirements' => 'Mampu mengoperasikan komputer dan memahami penggunaan browser.',
                'outcomes' => 'Mampu membuat halaman web responsif dan memahami alur kerja aplikasi web dasar.',
            ],
            [
                'title' => 'Dasar Kedokteran Klinis',
                'slug' => 'dasar-kedokteran-klinis',
                'description' => 'Pengenalan konsep pemeriksaan dasar, anamnesis, dan prinsip keselamatan pasien untuk pembelajaran awal.',
                'category_slug' => 'kedokteran',
                'instructor_email' => 'medical.instructor@example.com',
                'requirements' => 'Memiliki minat pada bidang kesehatan dan mampu mengikuti studi kasus sederhana.',
                'outcomes' => 'Memahami istilah klinis dasar, alur pemeriksaan awal, dan etika komunikasi dengan pasien.',
            ],
            [
                'title' => 'Teknologi Pertanian Modern',
                'slug' => 'teknologi-pertanian-modern',
                'description' => 'Mengenal penerapan teknologi, data sederhana, dan praktik budidaya untuk meningkatkan produktivitas pertanian.',
                'category_slug' => 'pertanian',
                'instructor_email' => 'agri.instructor@example.com',
                'requirements' => 'Tertarik pada pertanian dan pengelolaan lahan produktif.',
                'outcomes' => 'Mampu memahami konsep pertanian modern, pemantauan tanaman, dan perencanaan budidaya sederhana.',
            ],
        ];

        foreach ($courses as $course) {
            $categoryId = $categoryBySlug->get($course['category_slug']);
            $instructorId = $instructorByEmail->get($course['instructor_email']);

            if (! $categoryId || ! $instructorId) {
                continue;
            }

            Course::updateOrCreate(
                ['slug' => $course['slug']],
                [
                    'title' => $course['title'],
                    'slug' => $course['slug'],
                    'description' => $course['description'],
                    'category_id' => $categoryId,
                    'instructor_id' => $instructorId,
                    'thumbnail' => null,
                    'total_duration' => 0,
                    'requirements' => $course['requirements'],
                    'outcomes' => $course['outcomes'],
                ]
            );
        }
    }
}
