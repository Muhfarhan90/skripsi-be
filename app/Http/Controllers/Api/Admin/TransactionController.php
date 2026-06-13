<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Transaction\StoreTransactionRequest;
use App\Http\Requests\Admin\Transaction\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TransactionController extends Controller
{
    protected TransactionService $service;

    public function __construct(TransactionService $transactionService)
    {
        $this->service = $transactionService;
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 10);
        $status = trim((string) $request->query('status', ''));
        $transaction = $this->service->getAllForAdmin(
            $search,
            $perPage,
            $status !== '' ? $status : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Transaction list retrieved successfully',
            'data' => TransactionResource::collection($transaction),
            'meta' => [
                'current_page' => $transaction->currentPage(),
                'last_page' => $transaction->lastPage(),
                'per_page' => $transaction->perPage(),
                'total' => $transaction->total(),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $rows = $this->service->getAdminExportRows(
            $search,
            $status !== '' ? $status : null
        );

        return $this->streamCsvDownload(
            'admin-transactions-report',
            [
                'Kode Invoice',
                'Tanggal Transaksi',
                'Status Transaksi',
                'Nominal (IDR)',
                'Metode Pembayaran',
                'Referensi Pembayaran',
                'Order Code',
                'Status Order',
                'Nama Siswa',
                'Email Siswa',
                'Dibayar Pada',
                'Kedaluwarsa Pada',
            ],
            $rows
        );
    }

    public function store(StoreTransactionRequest $request)
    {
        $transaction = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Transaction created successfully',
            'data' => new TransactionResource($transaction),
        ]);
    }

    public function show(string $id)
    {
        $transaction = $this->service->findById((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Transaction retrieved successfully',
            'data' => new TransactionResource($transaction),
        ]);
    }

    public function update(UpdateTransactionRequest $request, string $id)
    {
        $transaction = $this->service->update((int) $id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Transaction updated successfully',
            'data' => new TransactionResource($transaction),
        ]);
    }

    public function destroy(string $id)
    {
        $this->service->delete((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Transaction deleted successfully',
        ]);
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
