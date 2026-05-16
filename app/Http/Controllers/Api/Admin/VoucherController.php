<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Voucher\StoreVoucherRequest;
use App\Http\Requests\Admin\Voucher\UpdateVoucherRequest;
use App\Http\Resources\VoucherResource;
use App\Services\VoucherService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    protected VoucherService $service;

    public function __construct(VoucherService $voucherService)
    {
        $this->service = $voucherService;
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 10);
        $voucher = $this->service->getAll($search, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Voucher list retrieved successfully',
            'data' => VoucherResource::collection($voucher),
            'meta' => [
                'current_page' => $voucher->currentPage(),
                'last_page' => $voucher->lastPage(),
                'per_page' => $voucher->perPage(),
                'total' => $voucher->total(),
            ],
        ]);
    }

    public function store(StoreVoucherRequest $request)
    {
        $voucher = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Voucher created successfully',
            'data' => new VoucherResource($voucher),
        ]);
    }

    public function show(string $id)
    {
        $voucher = $this->service->findById((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Voucher retrieved successfully',
            'data' => new VoucherResource($voucher),
        ]);
    }

    public function update(UpdateVoucherRequest $request, string $id)
    {
        $voucher = $this->service->update((int) $id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Voucher updated successfully',
            'data' => new VoucherResource($voucher),
        ]);
    }

    public function destroy(string $id)
    {
        $this->service->delete((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Voucher deleted successfully',
        ]);
    }
}
