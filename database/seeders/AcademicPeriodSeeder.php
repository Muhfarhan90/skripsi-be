<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use Illuminate\Database\Seeder;

class AcademicPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $periods = [
            [
                'code' => 'PRE-U-2026-A',
                'name' => 'Pre-University Period A 2026',
                'start_at' => $now->copy()->subMonths(2),
                'end_at' => $now->copy()->addMonths(4),
                'enrollment_open_at' => $now->copy()->subMonths(3),
                'enrollment_close_at' => $now->copy()->addMonth(),
                'is_active' => true,
            ],
            [
                'code' => 'PRE-U-2026-B',
                'name' => 'Pre-University Period B 2026',
                'start_at' => $now->copy()->addMonths(5),
                'end_at' => $now->copy()->addMonths(10),
                'enrollment_open_at' => $now->copy()->addMonths(3),
                'enrollment_close_at' => $now->copy()->addMonths(6),
                'is_active' => false,
            ],
            [
                'code' => 'PRE-U-2025-B',
                'name' => 'Pre-University Period B 2025',
                'start_at' => $now->copy()->subMonths(14),
                'end_at' => $now->copy()->subMonths(8),
                'enrollment_open_at' => $now->copy()->subMonths(16),
                'enrollment_close_at' => $now->copy()->subMonths(12),
                'is_active' => false,
            ],
        ];

        foreach ($periods as $period) {
            AcademicPeriod::updateOrCreate(
                ['code' => $period['code']],
                $period
            );
        }
    }
}
