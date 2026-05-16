<?php

namespace App\Services;

use App\Models\Voucher;

class VoucherService
{
    public function getAll(string $search = '', int $perPage = 10)
    {
        $perPage = max($perPage, 1);

        return Voucher::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('code', 'like', "%{$search}%")
                        ->orWhere('discount_type', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id)
    {
        return Voucher::findOrFail($id);
    }

    public function create(array $data)
    {
        return Voucher::create($data);
    }

    public function update(int $id, array $data)
    {
        $voucher = $this->findById($id);
        $voucher->update($data);

        return $voucher;
    }

    public function delete(int $id)
    {
        $voucher = $this->findById($id);
        $voucher->delete();

        return true;
    }
}
