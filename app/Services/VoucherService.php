<?php

namespace App\Services;

use App\Models\Voucher;

class VoucherService
{
    public function getAll()
    {
        return Voucher::latest()->paginate(10);
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