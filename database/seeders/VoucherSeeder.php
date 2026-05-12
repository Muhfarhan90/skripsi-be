<?php

namespace Database\Seeders;

use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vouchers = [
            [
                'code' => 'DISCOUNT10',
                'discount_type' => 'percentage',
                'discount_amount' => 10,
                'min_purchase' => 50,
                'max_discount' => 20,
                'usage_limit' => 100,
                'is_active' => true,
                'expired_at' => now()->addDays(30),
            ],
            [
                'code' => 'FIXED5',
                'discount_type' => 'fixed',
                'discount_amount' => 5,
                'min_purchase' => 20,
                'max_discount' => null,
                'usage_limit' => 50,
                'is_active' => true,
                'expired_at' => now()->addDays(15),
            ],
        ];

        foreach ($vouchers as $voucher) {
            Voucher::updateOrCreate(
                ['code' => $voucher['code']],
                $voucher
            );
        }
    }
}
