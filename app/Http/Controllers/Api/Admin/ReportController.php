<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function __construct(
        protected SalesReportService $service
    ) {
    }

    public function salesSummary(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'academic_period_id' => ['nullable', 'integer', 'exists:academic_periods,id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin sales summary retrieved successfully',
            'data' => $this->service->getSalesSummary($filters),
        ]);
    }

    public function exportSales(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'academic_period_id' => ['nullable', 'integer', 'exists:academic_periods,id'],
        ]);

        $rows = $this->service->getSalesExportRows($filters);

        return $this->streamCsvDownload(
            'admin-sales-report',
            [
                'Report Date',
                'Payment Status',
                'Order Code',
                'Invoice/Reference',
                'Student Name',
                'Student Email',
                'Course',
                'Instructor',
                'Academic Period',
                'Item Price (IDR)',
                'Allocated Discount (IDR)',
                'Net Item Revenue (IDR)',
                'Order Total (IDR)',
            ],
            $rows
        );
    }

    private function streamCsvDownload(string $filenamePrefix, array $headers, Collection $rows)
    {
        $filename = sprintf('%s-%s.csv', $filenamePrefix, now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
