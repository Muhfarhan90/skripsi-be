<?php

namespace App\Services;

use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\ForumPost;
use App\Models\ForumReply;
use App\Models\Order;
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

    public function getAdminDashboard(?string $startDate = null, ?string $endDate = null): array
    {
        $actor = auth()->user();
        $roleName = strtolower((string) ($actor?->role?->name ?? ''));

        // Parse dates using Carbon
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->subMonths(5)->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        // Calculate previous period for deltas
        $daysDiff = $start->diffInDays($end) + 1;
        $prevStart = $start->copy()->subDays($daysDiff);
        $prevEnd = $start->copy()->subSecond();

        if ($actor && $roleName === 'instructor') {
            return [
                'context' => 'instructor',
                'metrics' => $this->buildInstructorMetrics((int) $actor->id, $start, $end, $prevStart, $prevEnd),
                'recent_activities' => [],
                'instructor_overview' => $this->buildInstructorOverview((int) $actor->id),
                'instructor_charts_data' => $this->buildMonthlyInstructorChartsData((int) $actor->id, $start, $end),
            ];
        }

        return [
            'context' => 'admin',
            'metrics' => $this->buildAdminMetrics($start, $end, $prevStart, $prevEnd),
            'recent_activities' => $this->buildRecentActivities(),
            'instructor_overview' => null,
            'charts_data' => $this->buildMonthlyChartsData($start, $end),
        ];
    }

    private function buildAdminMetrics(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $now = now();

        $totalUsers = User::query()->count();
        $activeUsers = User::query()->where('is_active', true)->count();
        $currentPeriodUsers = User::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $previousPeriodUsers = User::query()
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $totalCourses = Course::query()->count();
        $activeOfferings = CourseOffering::query()->where('is_active', true)->count();
        $currentPeriodCourses = Course::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $previousPeriodCourses = Course::query()
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $successfulTransactionsThisMonth = $this->successfulTransactionsInPeriod($start, $end);
        $successfulTransactionsPrevMonth = $this->successfulTransactionsInPeriod($prevStart, $prevEnd);
        $successfulRevenueThisMonth = $this->successfulRevenueInPeriod($start, $end);

        $activeVouchers = Voucher::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('expired_at')
                    ->orWhere('expired_at', '>=', $now);
            })
            ->count();
        $previousActiveVouchers = Voucher::query()
            ->where('is_active', true)
            ->where('created_at', '<=', $prevEnd)
            ->where(function (Builder $query) use ($prevEnd) {
                $query->whereNull('expired_at')
                    ->orWhere('expired_at', '>=', $prevEnd);
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
            ], $this->buildTrend($currentPeriodUsers, $previousPeriodUsers)),
            array_merge([
                'label' => 'Total Courses',
                'value' => $this->formatInteger($totalCourses),
                'note' => $activeOfferings > 0
                    ? "{$this->formatInteger($activeOfferings)} offering aktif saat ini"
                    : 'Belum ada offering aktif',
            ], $this->buildTrend($currentPeriodCourses, $previousPeriodCourses)),
            array_merge([
                'label' => 'Transaksi Periode Ini',
                'value' => $this->formatInteger($successfulTransactionsThisMonth),
                'note' => "{$this->formatCurrency($successfulRevenueThisMonth)} transaksi sukses periode ini",
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

    private function buildInstructorMetrics(int $instructorId, Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $now = now();

        $totalCourses = Course::query()
            ->where('instructor_id', $instructorId)
            ->count();
        $currentPeriodCourses = Course::query()
            ->where('instructor_id', $instructorId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $previousPeriodCourses = Course::query()
            ->where('instructor_id', $instructorId)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $activeOfferings = CourseOffering::query()
            ->where('is_active', true)
            ->whereHas('course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();
        $currentPeriodOfferings = CourseOffering::query()
            ->where('is_active', true)
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();
        $previousPeriodOfferings = CourseOffering::query()
            ->where('is_active', true)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
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
        $currentPeriodEnrollments = Enrollment::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('courseOffering.course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();
        $previousPeriodEnrollments = Enrollment::query()
            ->whereBetween('created_at', [$prevStart, $prevEnd])
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
        $currentPeriodPendingReviews = AssignmentSubmission::query()
            ->where('status', 'submitted')
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('assignment.course', function (Builder $query) use ($instructorId) {
                $query->where('instructor_id', $instructorId);
            })
            ->count();
        $previousPeriodPendingReviews = AssignmentSubmission::query()
            ->where('status', 'submitted')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
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
            ], $this->buildTrend($currentPeriodCourses, $previousPeriodCourses)),
            array_merge([
                'label' => 'Offering Aktif',
                'value' => $this->formatInteger($activeOfferings),
                'note' => $currentPeriodOfferings > 0
                    ? "{$this->formatInteger($currentPeriodOfferings)} offering aktif dibuat periode ini"
                    : 'Belum ada offering aktif baru periode ini',
            ], $this->buildTrend($currentPeriodOfferings, $previousPeriodOfferings)),
            array_merge([
                'label' => 'Siswa Aktif',
                'value' => $this->formatInteger($activeStudents),
                'note' => $currentPeriodEnrollments > 0
                    ? "{$this->formatInteger($currentPeriodEnrollments)} enrollment baru periode ini"
                    : 'Belum ada enrollment baru periode ini',
            ], $this->buildTrend($currentPeriodEnrollments, $previousPeriodEnrollments)),
            array_merge([
                'label' => 'Review Submission',
                'value' => $this->formatInteger($pendingAssignmentReviews),
                'note' => $pendingAssignmentReviews > 0
                    ? 'Submission assignment menunggu ditinjau'
                    : 'Tidak ada submission yang menunggu review',
            ], $this->buildTrend($currentPeriodPendingReviews, $previousPeriodPendingReviews)),
        ];
    }

    private function buildMonthlyChartsData(Carbon $start, Carbon $end): array
    {
        $data = [];
        $studentRoleId = \App\Models\Role::where('name', 'user')->value('id');

        $diffInDays = $start->diffInDays($end);

        if ($diffInDays <= 31) {
            // Group by Day
            $current = $start->copy();
            while ($current->lte($end)) {
                $dayStart = $current->copy()->startOfDay();
                $dayEnd = $current->copy()->endOfDay();

                $studentsCount = User::query()
                    ->where('role_id', $studentRoleId)
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->count();

                $ordersCount = Order::query()
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->count();

                $revenue = (float) $this->successfulRevenueInPeriod($dayStart, $dayEnd);
                $transactionsCount = $this->successfulTransactionsInPeriod($dayStart, $dayEnd);

                $data[] = [
                    'label' => $current->format('d M'),
                    'students' => $studentsCount,
                    'orders' => $ordersCount,
                    'transactions' => $transactionsCount,
                    'revenue' => $revenue,
                ];

                $current->addDay();
            }
        } else {
            // Group by Month
            $current = $start->copy()->startOfMonth();
            while ($current->lte($end)) {
                $monthStart = $current->copy()->startOfMonth();
                if ($monthStart->lt($start)) {
                    $monthStart = $start->copy();
                }

                $monthEnd = $current->copy()->endOfMonth();
                if ($monthEnd->gt($end)) {
                    $monthEnd = $end->copy();
                }

                $studentsCount = User::query()
                    ->where('role_id', $studentRoleId)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count();

                $ordersCount = Order::query()
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count();

                $revenue = (float) $this->successfulRevenueInPeriod($monthStart, $monthEnd);
                $transactionsCount = $this->successfulTransactionsInPeriod($monthStart, $monthEnd);

                $data[] = [
                    'label' => $current->format('M Y'),
                    'students' => $studentsCount,
                    'orders' => $ordersCount,
                    'transactions' => $transactionsCount,
                    'revenue' => $revenue,
                ];

                $current->addMonth();
            }
        }

        return $data;
    }

    private function buildMonthlyInstructorChartsData(int $instructorId, Carbon $start, Carbon $end): array
    {
        $data = [];
        $diffInDays = $start->diffInDays($end);

        if ($diffInDays <= 31) {
            // Group by Day
            $current = $start->copy();
            while ($current->lte($end)) {
                $dayStart = $current->copy()->startOfDay();
                $dayEnd = $current->copy()->endOfDay();

                $enrollmentsCount = Enrollment::query()
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->whereHas('courseOffering.course', function (Builder $query) use ($instructorId) {
                        $query->where('instructor_id', $instructorId);
                    })
                    ->count();

                $forumPostsCount = ForumPost::query()
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->whereHas('course', function (Builder $query) use ($instructorId) {
                        $query->where('instructor_id', $instructorId);
                    })
                    ->count();

                $data[] = [
                    'label' => $current->format('d M'),
                    'enrollments' => $enrollmentsCount,
                    'forum_posts' => $forumPostsCount,
                ];

                $current->addDay();
            }
        } else {
            // Group by Month
            $current = $start->copy()->startOfMonth();
            while ($current->lte($end)) {
                $monthStart = $current->copy()->startOfMonth();
                if ($monthStart->lt($start)) {
                    $monthStart = $start->copy();
                }

                $monthEnd = $current->copy()->endOfMonth();
                if ($monthEnd->gt($end)) {
                    $monthEnd = $end->copy();
                }

                $enrollmentsCount = Enrollment::query()
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->whereHas('courseOffering.course', function (Builder $query) use ($instructorId) {
                        $query->where('instructor_id', $instructorId);
                    })
                    ->count();

                $forumPostsCount = ForumPost::query()
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->whereHas('course', function (Builder $query) use ($instructorId) {
                        $query->where('instructor_id', $instructorId);
                    })
                    ->count();

                $data[] = [
                    'label' => $current->format('M Y'),
                    'enrollments' => $enrollmentsCount,
                    'forum_posts' => $forumPostsCount,
                ];

                $current->addMonth();
            }
        }

        return $data;
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
