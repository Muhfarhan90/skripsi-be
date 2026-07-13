<?php

namespace App\Services;

use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuestionService
{
    private const AIKEN_OPTION_LABELS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function getAll()
    {
        return Question::latest()->paginate(10);
    }

    public function getByQuiz(int $quizId)
    {
        Quiz::findOrFail($quizId);

        return Question::with('options')
            ->where('quiz_id', $quizId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findById(int $id)
    {
        return Question::findOrFail($id);
    }

    public function findByIdInQuiz(int $quizId, int $id): Question
    {
        return Question::where('id', $id)
            ->where('quiz_id', $quizId)
            ->firstOrFail();
    }

    public function create(array $data)
    {
        $quizId = (int) ($data['quiz_id'] ?? 0);
        Quiz::findOrFail($quizId);
        $this->assertQuizHasNoAttempts($quizId);

        if (!isset($data['sort_order']) || $data['sort_order'] == 0) {
            $data['sort_order'] = Question::where('quiz_id', $quizId)
                ->max('sort_order') + 1;
        }

        return DB::transaction(function () use ($data, $quizId) {
            $question = Question::create($data);
            $this->rebalanceScores($quizId);
            return $question->refresh();
        });
    }

    public function createForQuiz(int $quizId, array $data): Question
    {
        Quiz::findOrFail($quizId);
        $this->assertQuizHasNoAttempts($quizId);

        return DB::transaction(function () use ($quizId, $data) {
            $payload = array_merge($data, [
                'quiz_id' => $quizId,
            ]);

            if (!isset($payload['sort_order']) || (int) $payload['sort_order'] <= 0) {
                $payload['sort_order'] = Question::where('quiz_id', $quizId)->max('sort_order') + 1;
            }

            $question = Question::create($payload);
            $this->rebalanceScores($quizId);

            return $question->refresh();
        });
    }

    public function update(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $question = $this->findById($id);
            $this->assertQuizHasNoAttempts((int) $question->quiz_id);

            if (isset($data['sort_order']) && $data['sort_order'] != $question->sort_order) {
                $this->handleReorder($question, $data['sort_order']);
            }

            $question->update($data);
            $this->rebalanceScores((int) $question->quiz_id);

            return $question->refresh();
        });
    }

    public function updateForQuiz(int $quizId, int $questionId, array $data): Question
    {
        return DB::transaction(function () use ($quizId, $questionId, $data) {
            $question = $this->findByIdInQuiz($quizId, $questionId);
            $this->assertQuizHasNoAttempts($quizId);

            if (array_key_exists('quiz_id', $data) && (int) $data['quiz_id'] !== $quizId) {
                throw ValidationException::withMessages([
                    'quiz_id' => ['Question must stay within the selected quiz'],
                ]);
            }

            if (isset($data['sort_order']) && (int) $data['sort_order'] !== (int) $question->sort_order) {
                $this->handleReorder($question, (int) $data['sort_order']);
            }

            $question->update($data);
            $this->rebalanceScores($quizId);

            return $question->refresh();
        });
    }

    public function reorderForQuiz(int $quizId, array $questionIds): void
    {
        Quiz::findOrFail($quizId);
        $this->assertQuizHasNoAttempts($quizId);

        $normalizedIds = array_values(array_map('intval', $questionIds));
        if (count($normalizedIds) === 0) {
            throw ValidationException::withMessages([
                'question_ids' => ['Question ids are required'],
            ]);
        }

        $existingIds = Question::where('quiz_id', $quizId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        sort($existingIds);
        $submittedIds = $normalizedIds;
        sort($submittedIds);

        if ($existingIds !== $submittedIds) {
            throw ValidationException::withMessages([
                'question_ids' => ['Submitted question ids must match all questions in this quiz'],
            ]);
        }

        DB::transaction(function () use ($quizId, $normalizedIds) {
            foreach ($normalizedIds as $index => $questionId) {
                Question::where('id', $questionId)
                    ->where('quiz_id', $quizId)
                    ->update(['sort_order' => $index + 1]);
            }

            $this->rebalanceScores($quizId);
        });
    }

    /**
     * @return array{filename: string, content: string}
     */
    public function exportQuestionBankAikenForQuiz(int $quizId): array
    {
        $quiz = Quiz::query()->findOrFail($quizId);
        $questions = $this->getByQuiz($quizId);

        return [
            'filename' => 'bank-soal-' . Str::slug((string) $quiz->title, '-') . '.txt',
            'content' => $this->buildQuestionBankAiken($questions),
        ];
    }

    /**
     * @return array{filename: string, content: string}
     */
    public function exportQuestionBankAikenTemplate(): array
    {
        return [
            'filename' => 'template-bank-soal-aiken.txt',
            'content' => implode(PHP_EOL, [
                'What is the correct answer to this question?',
                'A. Is it this one?',
                'B. Maybe this answer?',
                'C. Possibly this one?',
                'D. Must be this one!',
                'ANSWER: D',
                '',
                'Laravel is a PHP framework.',
                'A. True',
                'B. False',
                'ANSWER: A',
                '',
            ]),
        ];
    }

    /**
     * @return array{imported_questions: int, imported_options: int, mode: string}
     */
    public function importQuestionBankForQuiz(int $quizId, array $questionPayloads, string $mode = 'append'): array
    {
        $quiz = Quiz::query()->findOrFail($quizId);
        $this->assertQuizHasNoAttempts($quizId);

        $normalizedMode = in_array($mode, ['append', 'replace'], true) ? $mode : 'append';
        $normalizedQuestions = $this->normalizeImportedQuestionPayloads($questionPayloads);

        if ($normalizedQuestions === []) {
            throw ValidationException::withMessages([
                'questions' => ['Question bank tidak berisi question yang dapat diproses.'],
            ]);
        }

        return DB::transaction(function () use ($quiz, $normalizedQuestions, $normalizedMode) {
            if ($normalizedMode === 'replace') {
                $this->clearQuizQuestionBank((int) $quiz->id);
            }

            $sortOrder = (int) Question::query()
                ->where('quiz_id', $quiz->id)
                ->max('sort_order');

            $importedQuestions = 0;
            $importedOptions = 0;

            foreach ($normalizedQuestions as $questionPayload) {
                $sortOrder++;

                $question = Question::create([
                    'quiz_id' => $quiz->id,
                    'question_text' => $questionPayload['question_text'],
                    'image_url' => null,
                    'type' => $questionPayload['type'],
                    'score' => 0,
                    'sort_order' => $sortOrder,
                    'is_active' => $questionPayload['is_active'],
                ]);

                foreach ($questionPayload['options'] as $optionPayload) {
                    Option::create([
                        'question_id' => $question->id,
                        'option_text' => $optionPayload['option_text'],
                        'image_url' => null,
                        'is_correct' => $optionPayload['is_correct'],
                    ]);

                    $importedOptions++;
                }

                $importedQuestions++;
            }

            $this->rebalanceScores((int) $quiz->id);

            return [
                'imported_questions' => $importedQuestions,
                'imported_options' => $importedOptions,
                'mode' => $normalizedMode,
            ];
        });
    }

    /**
     * @return array{imported_questions: int, imported_options: int, mode: string}
     */
    public function importQuestionBankAikenForQuiz(int $quizId, UploadedFile $file, string $mode = 'append'): array
    {
        $questionPayloads = $this->parseAikenImportFile($file);

        if ($questionPayloads === []) {
            throw ValidationException::withMessages([
                'file' => ['File Aiken tidak berisi question yang dapat diproses.'],
            ]);
        }

        return $this->importQuestionBankForQuiz($quizId, $questionPayloads, $mode);
    }

    private function handleReorder(Question $question, int $newOrder)
    {
        $oldOrder = $question->sort_order;
        $quizId = $question->quiz_id;

        if ($newOrder > $oldOrder) {
            Question::where('quiz_id', $quizId)
                ->whereBetween('sort_order', [$oldOrder + 1, $newOrder])
                ->decrement('sort_order');
        } else {
            Question::where('quiz_id', $quizId)
                ->whereBetween('sort_order', [$newOrder, $oldOrder - 1])
                ->increment('sort_order');
        }
    }

    public function delete(int $id)
    {
        return DB::transaction(function () use ($id) {
            $question = $this->findById($id);
            $this->assertQuizHasNoAttempts((int) $question->quiz_id);
            $question->options()->delete();
            $deletedOrder = $question->sort_order;
            $quizId = (int) $question->quiz_id;

            $question->delete();

            // Menggeser agar tidak ada gap urutan setelah data dihapus
            Question::where('quiz_id', $quizId)
                ->where('sort_order', '>', $deletedOrder)
                ->decrement('sort_order');

            $this->rebalanceScores($quizId);

            return true;
        });
    }

    public function deleteForQuiz(int $quizId, int $questionId): bool
    {
        return DB::transaction(function () use ($quizId, $questionId) {
            $question = $this->findByIdInQuiz($quizId, $questionId);
            $this->assertQuizHasNoAttempts($quizId);
            $question->options()->delete();
            $deletedOrder = (int) $question->sort_order;
            $question->delete();

            Question::where('quiz_id', $quizId)
                ->where('sort_order', '>', $deletedOrder)
                ->decrement('sort_order');

            $this->rebalanceScores($quizId);

            return true;
        });
    }

    private function rebalanceScores(int $quizId): void
    {
        $questions = Question::where('quiz_id', $quizId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $total = $questions->count();
        if ($total === 0) {
            return;
        }

        $baseScore = intdiv(100, $total);
        $remainder = 100 % $total;

        foreach ($questions as $index => $question) {
            $score = $baseScore + ($index < $remainder ? 1 : 0);

            if ((int) $question->score !== $score) {
                $question->update(['score' => $score]);
            }
        }
    }

    private function assertQuizHasNoAttempts(int $quizId): void
    {
        //
    }

    private function buildQuestionBankAiken(iterable $questions): string
    {
        $blocks = [];

        foreach ($questions as $question) {
            if (! $question instanceof Question) {
                continue;
            }

            $options = $question->options
                ->sortBy('id')
                ->values();

            $this->assertQuestionSupportsAikenExport($question, $options->count());
            $correctLabel = $this->extractCorrectOptionLabel($question);

            $lines = [
                trim((string) $question->question_text),
            ];

            foreach ($options as $index => $option) {
                $lines[] = substr(self::AIKEN_OPTION_LABELS, $index, 1) . '. ' . trim((string) $option->option_text);
            }

            $lines[] = 'ANSWER: ' . $correctLabel;
            $blocks[] = implode(PHP_EOL, $lines);
        }

        return implode(PHP_EOL . PHP_EOL, $blocks) . PHP_EOL;
    }

    private function clearQuizQuestionBank(int $quizId): void
    {
        $questions = Question::query()
            ->where('quiz_id', $quizId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($questions as $question) {
            $question->options()->delete();
            $question->delete();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $questionPayloads
     * @return array<int, array{question_text: string, type: string, is_active: bool, options: array<int, array{option_text: string, is_correct: bool}>}>
     */
    private function normalizeImportedQuestionPayloads(array $questionPayloads): array
    {
        $normalizedQuestions = [];

        foreach ($questionPayloads as $index => $questionPayload) {
            $questionNumber = $index + 1;
            $questionText = trim((string) ($questionPayload['question_text'] ?? ''));
            $type = (string) ($questionPayload['type'] ?? 'multiple_choice');
            $isActive = array_key_exists('is_active', $questionPayload)
                ? (bool) $questionPayload['is_active']
                : true;
            $optionsPayload = $questionPayload['options'] ?? [];

            if ($questionText === '') {
                throw ValidationException::withMessages([
                    'questions' => ["Question ke-{$questionNumber} wajib memiliki question_text."],
                ]);
            }

            if (! in_array($type, ['multiple_choice', 'true_false'], true)) {
                throw ValidationException::withMessages([
                    'questions' => ["Question \"{$questionText}\" memiliki type yang tidak didukung."],
                ]);
            }

            if (! is_array($optionsPayload) || count($optionsPayload) < 2) {
                throw ValidationException::withMessages([
                    'questions' => ["Question \"{$questionText}\" harus memiliki minimal 2 option."],
                ]);
            }

            $normalizedOptions = [];
            foreach ($optionsPayload as $optionIndex => $optionPayload) {
                $optionText = trim((string) ($optionPayload['option_text'] ?? ''));

                if ($optionText === '') {
                    $optionNumber = $optionIndex + 1;
                    throw ValidationException::withMessages([
                        'questions' => ["Option ke-{$optionNumber} pada question \"{$questionText}\" wajib diisi."],
                    ]);
                }

                $normalizedOptions[] = [
                    'option_text' => $optionText,
                    'is_correct' => (bool) ($optionPayload['is_correct'] ?? false),
                ];
            }

            $correctOptionCount = collect($normalizedOptions)
                ->filter(fn (array $option) => $option['is_correct'])
                ->count();

            if ($correctOptionCount !== 1) {
                throw ValidationException::withMessages([
                    'questions' => ["Question \"{$questionText}\" harus memiliki tepat 1 jawaban benar."],
                ]);
            }

            if ($type === 'true_false') {
                $this->assertImportedTrueFalseOptions($questionText, $normalizedOptions);
            }

            $normalizedQuestions[] = [
                'question_text' => $questionText,
                'type' => $type,
                'is_active' => $isActive,
                'options' => $normalizedOptions,
            ];
        }

        return $normalizedQuestions;
    }

    /**
     * @return array<int, array{question_text: string, type: string, options: array<int, array{option_text: string, is_correct: bool}>}>
     */
    private function parseAikenImportFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());

        if ($content === false) {
            throw ValidationException::withMessages([
                'file' => ['File Aiken tidak dapat dibaca.'],
            ]);
        }

        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
        $totalLines = count($lines);
        $index = 0;
        $questions = [];

        while ($index < $totalLines) {
            while ($index < $totalLines && trim($lines[$index]) === '') {
                $index++;
            }

            if ($index >= $totalLines) {
                break;
            }

            $questionLines = [];
            while ($index < $totalLines) {
                $currentLine = trim($lines[$index]);

                if ($currentLine === '') {
                    $index++;
                    continue;
                }

                if ($this->isAikenOptionLine($currentLine)) {
                    break;
                }

                $questionLines[] = $currentLine;
                $index++;
            }

            if ($questionLines === []) {
                throw ValidationException::withMessages([
                    'file' => ['Format Aiken tidak valid: question_text tidak ditemukan sebelum pilihan jawaban.'],
                ]);
            }

            $options = [];
            while ($index < $totalLines) {
                $currentLine = trim($lines[$index]);

                if ($currentLine === '') {
                    $index++;
                    break;
                }

                if (preg_match('/^ANSWER\s*:\s*([A-Z])$/', $currentLine, $answerMatches) === 1) {
                    $correctLabel = $answerMatches[1];
                    $index++;

                    if ($options === []) {
                        throw ValidationException::withMessages([
                            'file' => ['Format Aiken tidak valid: pilihan jawaban tidak ditemukan sebelum ANSWER.'],
                        ]);
                    }

                    $optionLabels = array_column($options, 'label');
                    if (! in_array($correctLabel, $optionLabels, true)) {
                        throw ValidationException::withMessages([
                            'file' => ["Jawaban benar {$correctLabel} tidak cocok dengan label opsi pada question: " . implode(' ', $questionLines)],
                        ]);
                    }

                    $questions[] = [
                        'question_text' => implode(PHP_EOL, $questionLines),
                        'type' => $this->inferAikenQuestionType($options),
                        'options' => collect($options)
                            ->map(fn (array $option) => [
                                'option_text' => $option['option_text'],
                                'is_correct' => $option['label'] === $correctLabel,
                            ])
                            ->values()
                            ->all(),
                    ];

                    continue 2;
                }

                if (preg_match('/^([A-Z])[.)]\s+(.+)$/', $currentLine, $optionMatches) !== 1) {
                    throw ValidationException::withMessages([
                        'file' => ["Format Aiken tidak valid pada baris: {$currentLine}"],
                    ]);
                }

                $options[] = [
                    'label' => $optionMatches[1],
                    'option_text' => trim($optionMatches[2]),
                ];
                $index++;
            }

            throw ValidationException::withMessages([
                'file' => ['Format Aiken tidak valid: baris ANSWER tidak ditemukan untuk question "' . implode(' ', $questionLines) . '".'],
            ]);
        }

        return $questions;
    }

    private function assertQuestionSupportsAikenExport(Question $question, int $optionCount): void
    {
        if (! in_array((string) $question->type, ['multiple_choice', 'true_false'], true)) {
            throw ValidationException::withMessages([
                'quiz_id' => ['Question "' . $question->question_text . '" tidak kompatibel dengan export Aiken.'],
            ]);
        }

        if ($optionCount < 2) {
            throw ValidationException::withMessages([
                'quiz_id' => ['Question "' . $question->question_text . '" harus memiliki minimal 2 option untuk export Aiken.'],
            ]);
        }

        if ($optionCount > strlen(self::AIKEN_OPTION_LABELS)) {
            throw ValidationException::withMessages([
                'quiz_id' => ['Question "' . $question->question_text . '" memiliki option terlalu banyak untuk format Aiken.'],
            ]);
        }
    }

    /**
     * @param  array<int, array{option_text: string, is_correct: bool}>  $options
     */
    private function assertImportedTrueFalseOptions(string $questionText, array $options): void
    {
        if (count($options) !== 2) {
            throw ValidationException::withMessages([
                'questions' => ['Question "' . $questionText . '" bertipe true_false harus memiliki tepat 2 option.'],
            ]);
        }

        $normalizedOptionTexts = collect($options)
            ->pluck('option_text')
            ->map(fn (string $text) => Str::lower(trim($text)))
            ->sort()
            ->values()
            ->all();

        $isEnglishPair = $normalizedOptionTexts === ['false', 'true'];
        $isIndonesianPair = $normalizedOptionTexts === ['benar', 'salah'];

        if (! $isEnglishPair && ! $isIndonesianPair) {
            throw ValidationException::withMessages([
                'questions' => ['Question "' . $questionText . '" true_false hanya menerima option True/False atau Benar/Salah.'],
            ]);
        }
    }

    private function extractCorrectOptionLabel(Question $question): string
    {
        $options = $question->options
            ->sortBy('id')
            ->values();

        $correctIndexes = $options
            ->filter(fn (Option $option) => (bool) $option->is_correct)
            ->keys()
            ->values();

        if ($correctIndexes->count() !== 1) {
            throw ValidationException::withMessages([
                'quiz_id' => ['Question "' . $question->question_text . '" harus memiliki tepat 1 jawaban benar untuk export Aiken.'],
            ]);
        }

        return substr(self::AIKEN_OPTION_LABELS, (int) $correctIndexes[0], 1);
    }

    /**
     * @param  array<int, array{label: string, option_text: string}>  $options
     */
    private function inferAikenQuestionType(array $options): string
    {
        if (count($options) !== 2) {
            return 'multiple_choice';
        }

        $normalizedOptionTexts = collect($options)
            ->pluck('option_text')
            ->map(fn (string $text) => Str::lower(trim($text)))
            ->values()
            ->all();

        sort($normalizedOptionTexts);

        return $normalizedOptionTexts === ['false', 'true']
            ? 'true_false'
            : 'multiple_choice';
    }

    private function isAikenOptionLine(string $line): bool
    {
        return preg_match('/^[A-Z][.)]\s+.+$/', $line) === 1;
    }
}
