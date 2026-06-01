<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseOffering;
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
        return [
            'metrics' => $this->buildAdminMetrics(),
            'recent_activities' => $this->buildRecentActivities(),
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
