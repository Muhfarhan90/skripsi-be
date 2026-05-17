<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Option;
use App\Models\Order;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\Role;
use App\Models\Section;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CertificateDemoCourseSeeder extends Seeder
{
    private const COURSE_SLUG = 'certificate-demo-bootcamp';
    private const OFFERING_TITLE = 'Certificate Demo Bootcamp - Cohort A 2026';

    public function run(): void
    {
        $studentRoleId = Role::query()->where('name', 'user')->value('id');
        $reviewer = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $instructor = User::query()->where('email', 'instructor@example.com')->first() ?? $reviewer;

        $freshStudent = User::updateOrCreate(
            ['email' => 'student.certificate@example.com'],
            [
                'fullname' => 'Student Certificate Demo',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $studentRoleId,
            ]
        );

        $readyStudent = User::updateOrCreate(
            ['email' => 'student.certificate.ready@example.com'],
            [
                'fullname' => 'Student Certificate Ready',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $studentRoleId,
            ]
        );

        $categoryId = Category::query()->where('slug', 'technology')->value('id')
            ?? Category::updateOrCreate(
                ['slug' => 'technology'],
                ['name' => 'Technology']
            )->id;

        $course = Course::updateOrCreate(
            ['slug' => self::COURSE_SLUG],
            [
                'title' => 'Certificate Demo Bootcamp',
                'slug' => self::COURSE_SLUG,
                'description' => 'Course demo khusus untuk menguji alur belajar, quiz, assignment, dan klaim sertifikat secara end-to-end.',
                'category_id' => $categoryId,
                'instructor_id' => $instructor->id,
                'thumbnail' => null,
                'total_duration' => 315,
                'requirements' => "Mampu menggunakan browser.\nSiap mencoba alur LMS sebagai student.",
                'outcomes' => "Memahami alur belajar per section.\nMenyelesaikan quiz objective.\nMengirim assignment.\nMengklaim sertifikat.",
            ]
        );

        $sections = $this->seedSections($course);
        $lessons = $this->seedLessons($sections);
        $quizzes = $this->seedQuizzes($course, $sections);
        $questionMap = $this->seedQuestionsAndOptions($quizzes);
        $assignment = $this->seedRequiredAssignment($course, $sections['final-project'], $instructor);
        $offering = $this->seedOffering($course);

        $freshOrder = $this->seedCompletedOrder($freshStudent, $offering, 'FRESH');
        $readyOrder = $this->seedCompletedOrder($readyStudent, $offering, 'READY');

        $freshEnrollment = $this->seedEnrollment(
            $freshStudent,
            $offering,
            $freshOrder,
            now()->subDays(7),
            now()->addDays(90)
        );

        $readyEnrollment = $this->seedEnrollment(
            $readyStudent,
            $offering,
            $readyOrder,
            now()->subDays(14),
            now()->addDays(90)
        );

        $this->resetEnrollmentLearningState($freshEnrollment);
        $this->resetEnrollmentLearningState($readyEnrollment);

        $this->seedReadyScenario(
            $readyEnrollment,
            $lessons,
            $quizzes,
            $questionMap,
            $assignment,
            $reviewer
        );

        $enrollmentService = app(EnrollmentService::class);
        $enrollmentService->syncProgress($freshEnrollment->id);
        $enrollmentService->syncProgress($readyEnrollment->id);
    }

    /**
     * @return array<string, Section>
     */
    private function seedSections(Course $course): array
    {
        $rows = [
            ['key' => 'foundations', 'title' => 'Programming Foundations', 'sort_order' => 1],
            ['key' => 'control-flow', 'title' => 'Control Flow and Functions', 'sort_order' => 2],
            ['key' => 'final-project', 'title' => 'Final Project and Certification', 'sort_order' => 3],
        ];

        $sections = [];

        foreach ($rows as $row) {
            $sections[$row['key']] = Section::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'title' => $row['title'],
                ],
                [
                    'course_id' => $course->id,
                    'title' => $row['title'],
                    'sort_order' => $row['sort_order'],
                ]
            );
        }

        return $sections;
    }

    /**
     * @param  array<string, Section>  $sections
     * @return array<string, Lesson>
     */
    private function seedLessons(array $sections): array
    {
        $videoUrl = 'https://www.youtube.com/watch?v=ysz5S6PUM-U';

        $rows = [
            [
                'key' => 'intro-programming',
                'section_key' => 'foundations',
                'title' => 'How Programming Works',
                'description' => 'Memahami cara program menerima input, memproses data, dan menghasilkan output.',
                'duration' => 45,
                'sort_order' => 1,
            ],
            [
                'key' => 'variables-data',
                'section_key' => 'foundations',
                'title' => 'Variables and Data Types',
                'description' => 'Belajar menyimpan data, memilih tipe data, dan membaca nilai di dalam program.',
                'duration' => 50,
                'sort_order' => 2,
            ],
            [
                'key' => 'conditionals',
                'section_key' => 'control-flow',
                'title' => 'Conditionals and Branching',
                'description' => 'Menentukan alur eksekusi program dengan if, else, dan kondisi bertingkat.',
                'duration' => 55,
                'sort_order' => 1,
            ],
            [
                'key' => 'loops-functions',
                'section_key' => 'control-flow',
                'title' => 'Loops and Reusable Functions',
                'description' => 'Menggunakan perulangan dan function agar kode lebih efisien dan mudah dirawat.',
                'duration' => 60,
                'sort_order' => 2,
            ],
            [
                'key' => 'debugging',
                'section_key' => 'final-project',
                'title' => 'Debugging and Code Review',
                'description' => 'Mengenali bug umum dan membaca kode secara lebih sistematis.',
                'duration' => 45,
                'sort_order' => 1,
            ],
            [
                'key' => 'project-preparation',
                'section_key' => 'final-project',
                'title' => 'Preparing the Final Submission',
                'description' => 'Menyiapkan assignment akhir yang akan direview sebelum sertifikat dapat diklaim.',
                'duration' => 60,
                'sort_order' => 2,
            ],
        ];

        $lessons = [];

        foreach ($rows as $row) {
            $section = $sections[$row['section_key']];

            $lessons[$row['key']] = Lesson::updateOrCreate(
                [
                    'section_id' => $section->id,
                    'title' => $row['title'],
                ],
                [
                    'section_id' => $section->id,
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'type' => 'video',
                    'lesson_url' => $videoUrl,
                    'duration' => $row['duration'],
                    'sort_order' => $row['sort_order'],
                    'is_preview' => false,
                ]
            );
        }

        return $lessons;
    }

    /**
     * @param  array<string, Section>  $sections
     * @return array<string, Quiz>
     */
    private function seedQuizzes(Course $course, array $sections): array
    {
        $rows = [
            [
                'key' => 'foundations-quiz',
                'section_key' => 'foundations',
                'title' => 'Quiz 1: Programming Foundations',
                'description' => 'Mengukur pemahaman dasar tentang variabel, input, dan output.',
                'duration' => 25,
                'passing_score' => 70,
                'weight' => 10,
            ],
            [
                'key' => 'control-flow-quiz',
                'section_key' => 'control-flow',
                'title' => 'Quiz 2: Control Flow and Functions',
                'description' => 'Menguji pemahaman tentang percabangan, perulangan, dan function.',
                'duration' => 25,
                'passing_score' => 70,
                'weight' => 10,
            ],
            [
                'key' => 'final-quiz',
                'section_key' => 'final-project',
                'title' => 'Quiz 3: Final Objective Check',
                'description' => 'Quiz penutup untuk memastikan student siap melanjutkan ke submission akhir.',
                'duration' => 30,
                'passing_score' => 80,
                'weight' => 15,
            ],
        ];

        $quizzes = [];

        foreach ($rows as $row) {
            $section = $sections[$row['section_key']];

            $quizzes[$row['key']] = Quiz::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'section_id' => $section->id,
                    'title' => $row['title'],
                ],
                [
                    'course_id' => $course->id,
                    'section_id' => $section->id,
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'duration' => $row['duration'],
                    'passing_score' => $row['passing_score'],
                    'weight' => $row['weight'],
                    'is_active' => true,
                    'is_random' => false,
                    'max_attempts' => 3,
                    'open_at' => null,
                    'close_at' => null,
                ]
            );
        }

        return $quizzes;
    }

    /**
     * @param  array<string, Quiz>  $quizzes
     * @return array<string, array{question_id:int, correct_option_id:int}>
     */
    private function seedQuestionsAndOptions(array $quizzes): array
    {
        $rows = [
            [
                'key' => 'foundations-q1',
                'quiz_key' => 'foundations-quiz',
                'question_text' => 'Variable digunakan untuk menyimpan nilai di dalam program.',
                'type' => 'true_false',
                'score' => 50,
                'sort_order' => 1,
                'options' => [
                    ['text' => 'True', 'is_correct' => true],
                    ['text' => 'False', 'is_correct' => false],
                ],
            ],
            [
                'key' => 'foundations-q2',
                'quiz_key' => 'foundations-quiz',
                'question_text' => 'Manakah yang termasuk tipe data numerik?',
                'type' => 'multiple_choice',
                'score' => 50,
                'sort_order' => 2,
                'options' => [
                    ['text' => 'Integer', 'is_correct' => true],
                    ['text' => 'Paragraph', 'is_correct' => false],
                    ['text' => 'Screen', 'is_correct' => false],
                    ['text' => 'Folder', 'is_correct' => false],
                ],
            ],
            [
                'key' => 'control-flow-q1',
                'quiz_key' => 'control-flow-quiz',
                'question_text' => 'Percabangan if dipakai untuk menjalankan kode berdasarkan kondisi tertentu.',
                'type' => 'true_false',
                'score' => 50,
                'sort_order' => 1,
                'options' => [
                    ['text' => 'True', 'is_correct' => true],
                    ['text' => 'False', 'is_correct' => false],
                ],
            ],
            [
                'key' => 'control-flow-q2',
                'quiz_key' => 'control-flow-quiz',
                'question_text' => 'Function paling berguna untuk...',
                'type' => 'multiple_choice',
                'score' => 50,
                'sort_order' => 2,
                'options' => [
                    ['text' => 'Mengulang penulisan kode yang sama di banyak tempat', 'is_correct' => false],
                    ['text' => 'Memecah logika menjadi bagian yang bisa dipakai ulang', 'is_correct' => true],
                    ['text' => 'Menghapus semua bug secara otomatis', 'is_correct' => false],
                    ['text' => 'Mengganti semua variabel menjadi string', 'is_correct' => false],
                ],
            ],
            [
                'key' => 'final-q1',
                'quiz_key' => 'final-quiz',
                'question_text' => 'Array memungkinkan kita menyimpan banyak nilai dalam satu variabel.',
                'type' => 'true_false',
                'score' => 25,
                'sort_order' => 1,
                'options' => [
                    ['text' => 'True', 'is_correct' => true],
                    ['text' => 'False', 'is_correct' => false],
                ],
            ],
            [
                'key' => 'final-q2',
                'quiz_key' => 'final-quiz',
                'question_text' => 'Debugging berarti...',
                'type' => 'multiple_choice',
                'score' => 25,
                'sort_order' => 2,
                'options' => [
                    ['text' => 'Mencari dan memperbaiki bug pada program', 'is_correct' => true],
                    ['text' => 'Mengganti semua file menjadi PDF', 'is_correct' => false],
                    ['text' => 'Menonaktifkan seluruh validasi', 'is_correct' => false],
                    ['text' => 'Menghapus semua komentar kode', 'is_correct' => false],
                ],
            ],
            [
                'key' => 'final-q3',
                'quiz_key' => 'final-quiz',
                'question_text' => 'Pseudocode berguna untuk menjelaskan alur logika sebelum coding.',
                'type' => 'true_false',
                'score' => 25,
                'sort_order' => 3,
                'options' => [
                    ['text' => 'True', 'is_correct' => true],
                    ['text' => 'False', 'is_correct' => false],
                ],
            ],
            [
                'key' => 'final-q4',
                'quiz_key' => 'final-quiz',
                'question_text' => 'Langkah review akhir yang paling tepat adalah...',
                'type' => 'multiple_choice',
                'score' => 25,
                'sort_order' => 4,
                'options' => [
                    ['text' => 'Mengirim tugas tanpa membaca ulang', 'is_correct' => false],
                    ['text' => 'Memastikan output, struktur, dan penjelasan sudah konsisten', 'is_correct' => true],
                    ['text' => 'Menghapus bagian yang sulit dijelaskan', 'is_correct' => false],
                    ['text' => 'Membiarkan nama variabel acak agar singkat', 'is_correct' => false],
                ],
            ],
        ];

        $questionMap = [];

        foreach ($rows as $row) {
            $quiz = $quizzes[$row['quiz_key']];
            $question = Question::updateOrCreate(
                [
                    'quiz_id' => $quiz->id,
                    'question_text' => $row['question_text'],
                ],
                [
                    'quiz_id' => $quiz->id,
                    'question_text' => $row['question_text'],
                    'image_url' => null,
                    'type' => $row['type'],
                    'score' => $row['score'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ]
            );

            $correctOptionId = null;

            foreach ($row['options'] as $optionRow) {
                $option = Option::updateOrCreate(
                    [
                        'question_id' => $question->id,
                        'option_text' => $optionRow['text'],
                    ],
                    [
                        'question_id' => $question->id,
                        'option_text' => $optionRow['text'],
                        'image_url' => null,
                        'is_correct' => $optionRow['is_correct'],
                    ]
                );

                if ($optionRow['is_correct']) {
                    $correctOptionId = $option->id;
                }
            }

            $questionMap[$row['key']] = [
                'question_id' => $question->id,
                'correct_option_id' => (int) $correctOptionId,
            ];
        }

        return $questionMap;
    }

    private function seedRequiredAssignment(Course $course, Section $section, User $creator): Assignment
    {
        return Assignment::updateOrCreate(
            [
                'course_id' => $course->id,
                'title' => 'Final Reflection Project',
            ],
            [
                'course_id' => $course->id,
                'section_id' => $section->id,
                'created_by' => $creator->id,
                'title' => 'Final Reflection Project',
                'description' => 'Assignment akhir untuk memastikan student mampu merangkum pemahaman dasar pemrograman.',
                'instructions' => 'Tuliskan pseudocode aplikasi kasir sederhana, jelaskan alur input-proses-output, lalu sertakan refleksi singkat tentang apa yang sudah dipahami.',
                'due_at' => now()->addDays(30),
                'is_required_for_certificate' => true,
                'allow_resubmission' => true,
                'max_attempts' => 3,
                'status' => 'published',
            ]
        );
    }

    private function seedOffering(Course $course): CourseOffering
    {
        $period = AcademicPeriod::query()
            ->where('is_active', true)
            ->orderBy('start_at')
            ->first();

        if (! $period) {
            $period = AcademicPeriod::updateOrCreate(
                ['code' => 'CERT-DEMO-2026'],
                [
                    'name' => 'Certificate Demo Period 2026',
                    'start_at' => now()->subMonth(),
                    'end_at' => now()->addMonths(4),
                    'enrollment_open_at' => now()->subMonths(2),
                    'enrollment_close_at' => now()->addMonth(),
                    'is_active' => true,
                ]
            );
        }

        return CourseOffering::updateOrCreate(
            ['title' => self::OFFERING_TITLE],
            [
                'course_id' => $course->id,
                'academic_period_id' => $period->id,
                'title' => self::OFFERING_TITLE,
                'capacity' => 50,
                'price' => 299000,
                'discount_price' => 249000,
                'is_active' => true,
            ]
        );
    }

    private function seedCompletedOrder(User $student, CourseOffering $offering, string $suffix): Order
    {
        $price = (float) ($offering->discount_price ?: $offering->price ?: 0);
        $order = Order::updateOrCreate(
            ['order_code' => 'ORD-CERT-DEMO-' . $suffix],
            [
                'user_id' => $student->id,
                'voucher_id' => null,
                'order_code' => 'ORD-CERT-DEMO-' . $suffix,
                'subtotal' => $price,
                'discount' => 0,
                'tax' => 0,
                'admin_fee' => 0,
                'note' => 'Seeded certificate demo order',
                'grand_total' => $price,
                'status' => 'completed',
            ]
        );

        $order->items()->updateOrCreate(
            ['course_offering_id' => $offering->id],
            [
                'course_offering_id' => $offering->id,
                'price' => $price,
            ]
        );

        Transaction::updateOrCreate(
            ['invoice_code' => 'INV-CERT-DEMO-' . $suffix],
            [
                'order_id' => $order->id,
                'invoice_code' => 'INV-CERT-DEMO-' . $suffix,
                'payment_method' => 'Bank Transfer',
                'payment_channel' => 'BCA',
                'amount' => $price,
                'status' => 'success',
                'paid_at' => now()->subDays(2),
                'expired_at' => null,
                'verified_by' => null,
            ]
        );

        return $order;
    }

    private function seedEnrollment(
        User $student,
        CourseOffering $offering,
        Order $order,
        Carbon $startedAt,
        Carbon $endedAt
    ): Enrollment {
        return Enrollment::updateOrCreate(
            [
                'user_id' => $student->id,
                'course_offering_id' => $offering->id,
            ],
            [
                'user_id' => $student->id,
                'course_offering_id' => $offering->id,
                'order_id' => $order->id,
                'last_lesson_id' => null,
                'progress' => 0,
                'status' => 'active',
                'completed_at' => null,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'expired_at' => $endedAt,
            ]
        );
    }

    private function resetEnrollmentLearningState(Enrollment $enrollment): void
    {
        $attemptIds = QuizAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->pluck('id');

        if ($attemptIds->isNotEmpty()) {
            QuizAnswer::query()->whereIn('attempt_id', $attemptIds)->delete();
        }

        QuizAttempt::query()->where('enrollment_id', $enrollment->id)->delete();
        AssignmentSubmission::query()->where('enrollment_id', $enrollment->id)->delete();
        LessonProgress::query()->where('enrollment_id', $enrollment->id)->delete();
        $enrollment->certificate()->delete();

        $enrollment->update([
            'last_lesson_id' => null,
            'progress' => 0,
            'status' => 'active',
            'completed_at' => null,
        ]);
    }

    /**
     * @param  array<string, Lesson>  $lessons
     * @param  array<string, Quiz>  $quizzes
     * @param  array<string, array{question_id:int, correct_option_id:int}>  $questionMap
     */
    private function seedReadyScenario(
        Enrollment $enrollment,
        array $lessons,
        array $quizzes,
        array $questionMap,
        Assignment $assignment,
        User $reviewer
    ): void {
        $lessonSequence = array_values($lessons);
        $baseTime = now()->subDays(5);

        foreach ($lessonSequence as $index => $lesson) {
            LessonProgress::updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'lesson_id' => $lesson->id,
                ],
                [
                    'enrollment_id' => $enrollment->id,
                    'lesson_id' => $lesson->id,
                    'completed_at' => $baseTime->copy()->addHours($index),
                ]
            );
        }

        $quizQuestionKeys = [
            'foundations-quiz' => ['foundations-q1', 'foundations-q2'],
            'control-flow-quiz' => ['control-flow-q1', 'control-flow-q2'],
            'final-quiz' => ['final-q1', 'final-q2', 'final-q3', 'final-q4'],
        ];

        foreach ($quizzes as $quizKey => $quiz) {
            $attempt = QuizAttempt::updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'quiz_id' => $quiz->id,
                ],
                [
                    'enrollment_id' => $enrollment->id,
                    'quiz_id' => $quiz->id,
                    'total_score' => 100,
                    'status' => 'graded',
                    'started_at' => $baseTime->copy()->addDays(1),
                    'submitted_at' => $baseTime->copy()->addDays(1)->addMinutes(20),
                ]
            );

            foreach ($quizQuestionKeys[$quizKey] as $questionKey) {
                $question = $questionMap[$questionKey];

                QuizAnswer::updateOrCreate(
                    [
                        'attempt_id' => $attempt->id,
                        'question_id' => $question['question_id'],
                    ],
                    [
                        'attempt_id' => $attempt->id,
                        'question_id' => $question['question_id'],
                        'selected_option_id' => $question['correct_option_id'],
                        'answer_text' => null,
                        'is_correct' => true,
                        'score' => Question::query()->where('id', $question['question_id'])->value('score') ?? 0,
                    ]
                );
            }
        }

        AssignmentSubmission::updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'enrollment_id' => $enrollment->id,
                'attempt_no' => 1,
            ],
            [
                'assignment_id' => $assignment->id,
                'enrollment_id' => $enrollment->id,
                'user_id' => $enrollment->user_id,
                'attempt_no' => 1,
                'submission_text' => 'Saya membuat pseudocode aplikasi kasir sederhana dengan alur input barang, hitung total, lalu tampilkan struk. Saya juga menjelaskan penggunaan variabel, percabangan, dan function secara ringkas.',
                'attachment_url' => null,
                'status' => 'approved',
                'review_notes' => 'Struktur jawaban sudah rapi dan memenuhi kebutuhan minimum untuk syarat sertifikat.',
                'reviewed_by' => $reviewer->id,
                'submitted_at' => $baseTime->copy()->addDays(2),
                'reviewed_at' => $baseTime->copy()->addDays(2)->addHour(),
            ]
        );

        $lastLesson = end($lessonSequence) ?: null;

        $enrollment->update([
            'last_lesson_id' => $lastLesson?->id,
            'status' => 'active',
            'progress' => 0,
            'completed_at' => null,
        ]);
    }
}
