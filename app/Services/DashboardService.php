<?php

namespace App\Services;

use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\ForumPost;
use App\Models\ForumReply;
use App\Models\Review;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function getAdminDashboard(): array
    {
        $actor = auth()->user();
        $roleName = strtolower((string) ($actor?->role?->name ?? ''));

        if ($actor && $roleName === 'instructor') {
            return [
                'context' => 'instructor',
                'metrics' => $this->buildInstructorMetrics((int) $actor->id),
                'recent_activities' => [],
                'instructor_overview' => $this->buildInstructorOverview((int) $actor->id),
            ];
        }

        return [
            'context' => 'admin',
            'metrics' => $this->buildAdminMetrics(),
            'recent_activities' => $this->buildRecentActivities(),
            'instructor_overview' => null,
        ];
    }

    private function buildAdminMetrics(): array
    {
        $now = now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $currentMonthEnd = $now->copy()->endOfMonth();
        $previousMonthStart = $currentMonthStart->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $currentMonthStart->copy()->subSecond();

        $totalUsers = User::query()->count();
        $activeUsers = User::query()->where('is_active', true)->count();
        $currentMonthUsers = User::query()
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();
        $previousMonthUsers = User::query()
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $totalCourses = Course::query()->count();
        $activeOfferings = CourseOffering::query()->where('is_active', true)->count();
        $currentMonthCourses = Course::query()
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();
        $previousMonthCourses = Course::query()
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $successfulTransactionsThisMonth = $this->successfulTransactionsInPeriod($currentMonthStart, $currentMonthEnd);
        $successfulTransactionsPrevMonth = $this->successfulTransactionsInPeriod($previousMonthStart, $previousMonthEnd);
        $successfulRevenueThisMonth = $this->successfulRevenueInPeriod($currentMonthStart, $currentMonthEnd);

        $activeVouchers = Voucher::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('expired_at')
                    ->orWhere('expired_at', '>=', $now);
            })
            ->count();
        $previousActiveVouchers = Voucher::query()
            ->where('is_active', true)
            ->where('created_at', '<=', $previousMonthEnd)
            ->where(function (Builder $query) use ($previousMonthEnd) {
                $query->whereNull('expired_at')
                    ->orWhere('expired_at', '>=', $previousMonthEnd);
            })
            ->count();
        $expiringSoonVouchers = Voucher::query()
            ->where('is_active', true)
            ->whereNotNull('expired_at')
            ->whereBetween('expired_at', [$now, $now->copy()->addDays(7)])
            ->count();

        return [
            array_merge([
                'label' => 'Total Users',
                'value' => $this->formatInteger($totalUsers),
                'note' => $activeUsers > 0
                    ? "{$this->formatInteger($activeUsers)} akun aktif saat ini"
                    : 'Belum ada akun aktif',
            ], $this->buildTrend($currentMonthUsers, $previousMonthUsers)),
            array_merge([
                'label' => 'Total Courses',
                'value' => $this->formatInteger($totalCourses),
                'note' => $activeOfferings > 0
                    ? "{$this->formatInteger($activeOfferings)} offering aktif saat ini"
                    : 'Belum ada offering aktif',
            ], $this->buildTrend($currentMonthCourses, $previousMonthCourses)),
            array_merge([
                'label' => 'Transaksi Bulan Ini',
                'value' => $this->formatInteger($successfulTransactionsThisMonth),
                'note' => "{$this->formatCurrency($successfulRevenueThisMonth)} transaksi sukses bulan ini",
            ], $this->buildTrend($successfulTransactionsThisMonth, $successfulTransactionsPrevMonth)),
            array_merge([
                'label' => 'Voucher Aktif',
                'value' => $this->formatInteger($activeVouchers),
                'note' => $expiringSoonVouchers > 0
                    ? "{$this->formatInteger($expiringSoonVouchers)} voucher berakhir <= 7 hari"
                    : 'Tidak ada voucher yang segera berakhir',
            ], $this->buildTrend($activeVouchers, $previousActiveVouchers)),
        ];
    }

    private function buildRecentActivities(int $limit = 8): array
    {
        return $this->activityLogService->getRecentAdminActivities($limit);
    }

    private function buildInstructorMetrics(int $instructorId): array
    {
        $now = now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $currentMonthEnd = $now->copy()->endOfMonth();
        $previousMonthStart = $currentMonthStart->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $currentMonthStart->copy()->subSecond();

        $totalCourses = Course::query()
            ->where('instructor_id', $instructorId)
            ->count();
        $currentMonthCourses = Course::query()
            ->where('instructor_id', $instructorId)
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();
        $previousMonthCourses = Course::query()
            ->where('instructor_id', $instructorId)
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $activeOfferings = CourseOffering::query()
            ->where('is_active', true)
            ->whereHas('course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();
        $currentMonthOfferings = CourseOffering::query()
            ->where('is_active', true)
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();
        $previousMonthOfferings = CourseOffering::query()
            ->where('is_active', true)
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->whereHas('course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();

        $activeStudents = Enrollment::query()
            ->whereIn('status', ['active', 'completed'])
            ->whereHas('courseOffering.course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->distinct('user_id')
            ->count('user_id');
        $currentMonthEnrollments = Enrollment::query()
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('courseOffering.course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();
        $previousMonthEnrollments = Enrollment::query()
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->whereHas('courseOffering.course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();

        $pendingAssignmentReviews = AssignmentSubmission::query()
            ->where('status', 'submitted')
            ->whereHas('assignment.course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();
        $currentMonthPendingReviews = AssignmentSubmission::query()
            ->where('status', 'submitted')
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('assignment.course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();
        $previousMonthPendingReviews = AssignmentSubmission::query()
            ->where('status', 'submitted')
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->whereHas('assignment.course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();

        return [
            array_merge([
                'label' => 'Total Course',
                'value' => $this->formatInteger($totalCourses),
                'note' => $activeOfferings > 0
                    ? "{$this->formatInteger($activeOfferings)} offering aktif sedang berjalan"
                    : 'Belum ada offering aktif',
            ], $this->buildTrend($currentMonthCourses, $previousMonthCourses)),
            array_merge([
                'label' => 'Offering Aktif',
                'value' => $this->formatInteger($activeOfferings),
                'note' => $currentMonthOfferings > 0
                    ? "{$this->formatInteger($currentMonthOfferings)} offering aktif dibuat bulan ini"
                    : 'Belum ada offering aktif baru bulan ini',
            ], $this->buildTrend($currentMonthOfferings, $previousMonthOfferings)),
            array_merge([
                'label' => 'Siswa Aktif',
                'value' => $this->formatInteger($activeStudents),
                'note' => $currentMonthEnrollments > 0
                    ? "{$this->formatInteger($currentMonthEnrollments)} enrollment baru bulan ini"
                    : 'Belum ada enrollment baru bulan ini',
            ], $this->buildTrend($currentMonthEnrollments, $previousMonthEnrollments)),
            array_merge([
                'label' => 'Review Submission',
                'value' => $this->formatInteger($pendingAssignmentReviews),
                'note' => $pendingAssignmentReviews > 0
                    ? 'Submission assignment menunggu ditinjau'
                    : 'Tidak ada submission yang menunggu review',
            ], $this->buildTrend($currentMonthPendingReviews, $previousMonthPendingReviews)),
        ];
    }

    private function buildInstructorOverview(int $instructorId): array
    {
        $now = now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $currentMonthEnd = $now->copy()->endOfMonth();

        $courses = Course::query()
            ->with(['category:id,name', 'courseOfferings:id,course_id,is_active'])
            ->withCount([
                'courseOfferings as active_offerings_count' => function (Builder $query) {
                    $query->where('is_active', true);
                },
                'enrollments as total_enrollments_count',
                'enrollments as active_students_count' => function (Builder $query) {
                    $query->whereIn('enrollments.status', ['active', 'completed']);
                },
                'enrollments as completed_students_count' => function (Builder $query) {
                    $query->where('enrollments.status', 'completed');
                },
                'reviews as total_reviews_count',
            ])
            ->withAvg('reviews as average_rating', 'rating')
            ->where('instructor_id', $instructorId)
            ->orderByDesc('active_students_count')
            ->orderByDesc('total_enrollments_count')
            ->orderBy('title')
            ->limit(6)
            ->get()
            ->map(function (Course $course) {
                $activeOffering = $course->courseOfferings->firstWhere('is_active', true);

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'category_name' => $course->category?->name,
                    'active_offering_id' => $activeOffering?->id,
                    'active_offerings_count' => (int) ($course->active_offerings_count ?? 0),
                    'total_enrollments_count' => (int) ($course->total_enrollments_count ?? 0),
                    'active_students_count' => (int) ($course->active_students_count ?? 0),
                    'completed_students_count' => (int) ($course->completed_students_count ?? 0),
                    'total_reviews_count' => (int) ($course->total_reviews_count ?? 0),
                    'average_rating' => $course->average_rating !== null ? round((float) $course->average_rating, 1) : null,
                ];
            })
            ->values()
            ->all();

        $reviewSummary = Review::query()
            ->selectRaw('COUNT(*) as total_reviews, AVG(rating) as average_rating')
            ->whereHas('course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->first();

        $forumPostsThisMonth = ForumPost::query()
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();

        $forumRepliesThisMonth = ForumReply::query()
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('post.course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();

        return [
            'total_reviews' => (int) ($reviewSummary?->total_reviews ?? 0),
            'average_rating' => $reviewSummary?->average_rating !== null
                ? round((float) $reviewSummary->average_rating, 1)
                : null,
            'forum_posts_this_month' => $forumPostsThisMonth,
            'forum_replies_this_month' => $forumRepliesThisMonth,
            'courses' => $courses,
        ];
    }

    private function successfulTransactionsInPeriod(Carbon $start, Carbon $end): int
    {
        return $this->buildSuccessfulTransactionPeriodQuery($start, $end)->count();
    }

    private function successfulRevenueInPeriod(Carbon $start, Carbon $end): float
    {
        return (float) $this->buildSuccessfulTransactionPeriodQuery($start, $end)->sum('amount');
    }

    private function buildSuccessfulTransactionPeriodQuery(Carbon $start, Carbon $end): Builder
    {
        return Transaction::query()
            ->where('status', 'success')
            ->where(function (Builder $query) use ($start, $end) {
                $query->whereBetween('paid_at', [$start, $end])
                    ->orWhere(function (Builder $nestedQuery) use ($start, $end) {
                        $nestedQuery->whereNull('paid_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            });
    }

    private function buildTrend(int $current, int $previous): array
    {
        if ($current === $previous) {
            return [
                'delta' => '0%',
                'deltaTone' => 'neutral',
            ];
        }

        if ($previous === 0) {
            return [
                'delta' => $current > 0 ? "+{$current}" : '0%',
                'deltaTone' => $current > 0 ? 'positive' : 'neutral',
            ];
        }

        $change = (($current - $previous) / $previous) * 100;
        $formattedChange = rtrim(rtrim(number_format(abs($change), 1, '.', ''), '0'), '.');

        return [
            'delta' => sprintf('%s%s%%', $change > 0 ? '+' : '-', $formattedChange),
            'deltaTone' => $change > 0 ? 'positive' : 'negative',
        ];
    }
    private function formatInteger(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function formatCurrency(float $value): string
    {
        return 'Rp' . number_format($value, 0, ',', '.');
    }
}
