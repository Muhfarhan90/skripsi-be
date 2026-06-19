<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courses = Course::query()
            ->get(['id', 'slug', 'instructor_id'])
            ->keyBy('slug');
        $fallbackCreatorId = User::where('email', 'instructor@example.com')->value('id')
            ?? User::where('email', 'admin@example.com')->value('id');
        $now = now();

        $rows = [
            [
                'course_slug' => 'pemrograman-web',
                'title' => 'Tugas Project Pemrograman Web',
                'description' => 'Project sederhana untuk menguji pemahaman dasar pembuatan halaman web.',
                'instructions' => 'Buat satu halaman web responsif berisi profil produk atau layanan. Sertakan HTML, CSS, dan JavaScript sederhana jika diperlukan.',
                'due_at' => $now->copy()->addDays(30),
                'is_required_for_certificate' => true,
                'allow_resubmission' => true,
                'max_attempts' => 3,
                'status' => 'published',
            ],
            [
                'course_slug' => 'dasar-kedokteran-klinis',
                'title' => 'Tugas Studi Kasus Anamnesis',
                'description' => 'Latihan memahami alur anamnesis awal melalui studi kasus sederhana.',
                'instructions' => 'Baca skenario pasien, lalu tuliskan pertanyaan anamnesis utama, dugaan awal, dan edukasi keselamatan pasien secara ringkas.',
                'due_at' => $now->copy()->addDays(21),
                'is_required_for_certificate' => true,
                'allow_resubmission' => true,
                'max_attempts' => 3,
                'status' => 'published',
            ],
            [
                'course_slug' => 'teknologi-pertanian-modern',
                'title' => 'Tugas Rencana Budidaya Modern',
                'description' => 'Latihan menyusun rencana budidaya sederhana dengan pendekatan teknologi dan pemantauan data.',
                'instructions' => 'Pilih satu komoditas, lalu susun rencana budidaya, kebutuhan monitoring, dan indikator keberhasilan panen.',
                'due_at' => $now->copy()->addDays(25),
                'is_required_for_certificate' => true,
                'allow_resubmission' => true,
                'max_attempts' => 3,
                'status' => 'published',
            ],
        ];

        foreach ($rows as $row) {
            $course = $courses->get($row['course_slug']);

            if (! $course) {
                continue;
            }

            $sectionId = Section::query()
                ->where('course_id', $course->id)
                ->where('title', 'Evaluasi dan Tugas')
                ->value('id');

            Assignment::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'title' => $row['title'],
                ],
                [
                    'course_id' => $course->id,
                    'section_id' => $sectionId,
                    'created_by' => $course->instructor_id ?: $fallbackCreatorId,
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
