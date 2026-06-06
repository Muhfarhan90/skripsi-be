<?php

namespace App\Services;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReportService
{
    private const REPORT_TIMEZONE = 'Asia/Jakarta';

    public function getSalesSummary(array $filters = []): array
    {
        $resolvedFilters = $this->resolveFilters($filters);
        $rows = $this->buildNormalizedRows($resolvedFilters);
        $successfulRows = $rows->where('payment_status', 'success')->values();

        return [
            'filters' => [
                'from' => $resolvedFilters['from']->format('Y-m-d'),
                'to' => $resolvedFilters['to']->format('Y-m-d'),
                'academic_period_id' => $resolvedFilters['academic_period_id'],
                'timezone' => self::REPORT_TIMEZONE,
            ],
            'summary' => [
                'total_sales' => round((float) $successfulRows->sum('net_item_revenue'), 2),
                'successful_transactions' => $successfulRows->pluck('transaction_id')->unique()->count(),
                'completed_orders' => $successfulRows
                    ->filter(fn (array $row) => $row['order_status'] === 'completed')
                    ->pluck('order_id')
                    ->unique()
                    ->count(),
                'unique_buyers' => $successfulRows->pluck('student_id')->filter()->unique()->count(),
            ],
            'status_breakdown' => $this->buildStatusBreakdown($rows)->all(),
            'top_courses' => $this->buildTopCourses($successfulRows)->all(),
        ];
    }

    public function getSalesExportRows(array $filters = []): Collection
    {
        $resolvedFilters = $this->resolveFilters($filters);

        return $this->buildNormalizedRows($resolvedFilters)->map(function (array $row) {
            return [
                'Report Date' => $row['report_date_local'],
                'Payment Status' => $row['payment_status'],
                'Order Code' => $row['order_code'],
                'Invoice/Reference' => $row['payment_reference_label'],
                'Student Name' => $row['student_name'],
                'Student Email' => $row['student_email'],
                'Course' => $row['course_title'],
                'Instructor' => $row['instructor_name'],
                'Academic Period' => $this->formatAcademicPeriodLabel(
                    $row['academic_period_name'],
                    $row['academic_period_code'],
                ),
                'Item Price (IDR)' => $this->formatExportAmount($row['item_price']),
                'Allocated Discount (IDR)' => $this->formatExportAmount($row['allocated_discount']),
                'Net Item Revenue (IDR)' => $this->formatExportAmount($row['net_item_revenue']),
                'Order Total (IDR)' => $this->formatExportAmount($row['order_total']),
            ];
        });
    }

    private function resolveFilters(array $filters): array
    {
        $now = now(self::REPORT_TIMEZONE);
        $from = ! empty($filters['from'])
            ? Carbon::createFromFormat('Y-m-d', (string) $filters['from'], self::REPORT_TIMEZONE)->startOfDay()
            : $now->copy()->startOfMonth()->startOfDay();
        $to = ! empty($filters['to'])
            ? Carbon::createFromFormat('Y-m-d', (string) $filters['to'], self::REPORT_TIMEZONE)->endOfDay()
            : $now->copy()->endOfDay();

        if ($from->gt($to)) {
            throw ValidationException::withMessages([
                'from' => ['Tanggal mulai tidak boleh lebih besar dari tanggal akhir.'],
                'to' => ['Tanggal akhir tidak boleh lebih kecil dari tanggal mulai.'],
            ]);
        }

        return [
            'from' => $from,
            'to' => $to,
            'from_utc' => $from->copy()->utc(),
            'to_utc' => $to->copy()->utc(),
            'academic_period_id' => isset($filters['academic_period_id']) ? (int) $filters['academic_period_id'] : null,
        ];
    }

    private function buildCanonicalTransactionsSubquery(): QueryBuilder
    {
        return DB::table('transactions')
            ->selectRaw('
                transactions.id,
                transactions.order_id,
                transactions.invoice_code,
                transactions.external_id,
                transactions.payment_reference,
                transactions.amount,
                transactions.status,
                transactions.paid_at,
                transactions.created_at,
                COALESCE(transactions.paid_at, transactions.created_at) as report_date
            ')
            ->whereNotExists(function (QueryBuilder $query) {
                $query->selectRaw('1')
                    ->from('transactions as newer')
                    ->whereColumn('newer.order_id', 'transactions.order_id')
                    ->where(function (QueryBuilder $builder) {
                        $builder->whereRaw(
                            'COALESCE(newer.paid_at, newer.created_at) > COALESCE(transactions.paid_at, transactions.created_at)'
                        )->orWhere(function (QueryBuilder $tieBreaker) {
                            $tieBreaker->whereRaw(
                                'COALESCE(newer.paid_at, newer.created_at) = COALESCE(transactions.paid_at, transactions.created_at)'
                            )->whereColumn('newer.id', '>', 'transactions.id');
                        });
                    });
            });
    }

    private function fetchReportRows(array $filters): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->joinSub($this->buildCanonicalTransactionsSubquery(), 'canonical_transactions', function (JoinClause $join) {
                $join->on('canonical_transactions.order_id', '=', 'orders.id');
            })
            ->join('course_offerings', 'course_offerings.id', '=', 'order_items.course_offering_id')
            ->join('courses', 'courses.id', '=', 'course_offerings.course_id')
            ->leftJoin('academic_periods', 'academic_periods.id', '=', 'course_offerings.academic_period_id')
            ->leftJoin('users as students', 'students.id', '=', 'orders.user_id')
            ->leftJoin('users as instructors', 'instructors.id', '=', 'courses.instructor_id')
            ->where('orders.status', '!=', 'cart')
            ->whereBetween('canonical_transactions.report_date', [$filters['from_utc'], $filters['to_utc']])
            ->orderByDesc('canonical_transactions.report_date')
            ->orderByDesc('orders.id')
            ->orderBy('order_items.id')
            ->selectRaw('
                order_items.id as order_item_id,
                order_items.course_offering_id,
                order_items.price as item_price,
                orders.id as order_id,
                orders.user_id as student_id,
                orders.order_code,
                orders.subtotal as order_subtotal,
                orders.discount as order_discount,
                orders.grand_total as order_total,
                orders.status as order_status,
                students.fullname as student_name,
                students.email as student_email,
                canonical_transactions.id as transaction_id,
                canonical_transactions.invoice_code,
                canonical_transactions.external_id,
                canonical_transactions.payment_reference,
                canonical_transactions.amount as payment_amount,
                canonical_transactions.status as payment_status,
                canonical_transactions.report_date,
                courses.id as course_id,
                courses.title as course_title,
                courses.slug as course_slug,
                instructors.fullname as instructor_name,
                academic_periods.id as academic_period_id,
                academic_periods.name as academic_period_name,
                academic_periods.code as academic_period_code
            ')
            ->get();
    }

    private function buildNormalizedRows(array $filters): Collection
    {
        $normalizedRows = $this->fetchReportRows($filters)
            ->groupBy('order_id')
            ->flatMap(fn (Collection $rows) => $this->normalizeOrderRows($rows))
            ->values();

        if ($filters['academic_period_id']) {
            return $normalizedRows
                ->where('academic_period_id', $filters['academic_period_id'])
                ->values();
        }

        return $normalizedRows;
    }

    private function normalizeOrderRows(Collection $rows): array
    {
        $sortedRows = $rows->sortBy('order_item_id')->values();
        $order = $sortedRows->first();
        $subtotalCents = $this->toCents($order->order_subtotal);
        $discountCents = $this->toCents($order->order_discount);
        $allocatedDiscountCents = 0;
        $totalRows = $sortedRows->count();

        return $sortedRows->map(function ($row, int $index) use (
            $subtotalCents,
            $discountCents,
            &$allocatedDiscountCents,
            $totalRows
        ) {
            $itemPriceCents = $this->toCents($row->item_price);

            if ($discountCents <= 0 || $subtotalCents <= 0) {
                $rowDiscountCents = 0;
            } elseif ($index === $totalRows - 1) {
                $rowDiscountCents = max($discountCents - $allocatedDiscountCents, 0);
            } else {
                $rowDiscountCents = (int) floor(($discountCents * $itemPriceCents) / $subtotalCents);
                $allocatedDiscountCents += $rowDiscountCents;
            }

            $netRevenueCents = max($itemPriceCents - $rowDiscountCents, 0);
            $reportDate = Carbon::parse((string) $row->report_date, 'UTC')->timezone(self::REPORT_TIMEZONE);
            $paymentReferenceLabel = $row->payment_reference ?: ($row->external_id ?: $row->invoice_code);

            return [
                'order_item_id' => (int) $row->order_item_id,
                'order_id' => (int) $row->order_id,
                'student_id' => $row->student_id ? (int) $row->student_id : null,
                'transaction_id' => (int) $row->transaction_id,
                'course_offering_id' => $row->course_offering_id ? (int) $row->course_offering_id : null,
                'course_id' => $row->course_id ? (int) $row->course_id : null,
                'academic_period_id' => $row->academic_period_id ? (int) $row->academic_period_id : null,
                'order_code' => (string) $row->order_code,
                'order_status' => (string) $row->order_status,
                'payment_status' => (string) $row->payment_status,
                'payment_reference_label' => (string) $paymentReferenceLabel,
                'invoice_code' => (string) $row->invoice_code,
                'student_name' => (string) ($row->student_name ?? ''),
                'student_email' => (string) ($row->student_email ?? ''),
                'course_title' => (string) ($row->course_title ?? ''),
                'course_slug' => (string) ($row->course_slug ?? ''),
                'instructor_name' => (string) ($row->instructor_name ?? ''),
                'academic_period_name' => $row->academic_period_name ? (string) $row->academic_period_name : null,
                'academic_period_code' => $row->academic_period_code ? (string) $row->academic_period_code : null,
                'report_date_local' => $reportDate->format('Y-m-d H:i:s'),
                'item_price' => $this->fromCents($itemPriceCents),
                'allocated_discount' => $this->fromCents($rowDiscountCents),
                'net_item_revenue' => $this->fromCents($netRevenueCents),
                'order_total' => $this->fromCents($this->toCents($row->order_total)),
            ];
        })->all();
    }

    private function buildStatusBreakdown(Collection $rows): Collection
    {
        $rowsByStatus = $rows->groupBy('payment_status');
        $statuses = [
            'pending' => 'Pending',
            'success' => 'Success',
            'failed' => 'Failed',
        ];

        return collect($statuses)->map(function (string $label, string $status) use ($rowsByStatus) {
            $statusRows = $rowsByStatus->get($status, collect());

            return [
                'status' => $status,
                'label' => $label,
                'count' => $statusRows->pluck('transaction_id')->unique()->count(),
                'amount' => round((float) $statusRows->sum('net_item_revenue'), 2),
            ];
        })->values();
    }

    private function buildTopCourses(Collection $rows): Collection
    {
        return $rows
            ->groupBy('course_offering_id')
            ->map(function (Collection $groupedRows) {
                $first = $groupedRows->first();

                return [
                    'course_offering_id' => $first['course_offering_id'],
                    'course_id' => $first['course_id'],
                    'course_title' => $first['course_title'],
                    'course_slug' => $first['course_slug'],
                    'instructor_name' => $first['instructor_name'],
                    'academic_period_id' => $first['academic_period_id'],
                    'academic_period_name' => $first['academic_period_name'],
                    'academic_period_code' => $first['academic_period_code'],
                    'units_sold' => $groupedRows->count(),
                    'unique_buyers' => $groupedRows->pluck('student_id')->filter()->unique()->count(),
                    'revenue' => round((float) $groupedRows->sum('net_item_revenue'), 2),
                ];
            })
            ->sort(function (array $left, array $right) {
                if ($left['revenue'] !== $right['revenue']) {
                    return $right['revenue'] <=> $left['revenue'];
                }

                if ($left['units_sold'] !== $right['units_sold']) {
                    return $right['units_sold'] <=> $left['units_sold'];
                }

                return strcmp($left['course_title'], $right['course_title']);
            })
            ->values()
            ->take(10);
    }

    private function formatAcademicPeriodLabel(?string $name, ?string $code): string
    {
        if ($code && $name) {
            return "{$code} - {$name}";
        }

        return $name ?: ($code ?: '');
    }

    private function formatExportAmount(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function toCents(float|int|string|null $value): int
    {
        return (int) round((float) ($value ?? 0) * 100);
    }

    private function fromCents(int $value): float
    {
        return round($value / 100, 2);
    }
}
