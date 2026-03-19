<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Transaction\StoreTransactionRequest;
use App\Http\Requests\Admin\Transaction\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;

class TransactionController extends Controller
{
    protected TransactionService $service;

    public function __construct(TransactionService $transactionService)
    {
        $this->service = $transactionService;
    }

    public function index()
    {
        $transaction = $this->service->getAll();

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
}