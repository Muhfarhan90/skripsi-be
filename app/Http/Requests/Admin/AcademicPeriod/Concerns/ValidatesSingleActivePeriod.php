<?php

namespace App\Http\Requests\Admin\AcademicPeriod\Concerns;

use App\Models\AcademicPeriod;
use Illuminate\Validation\Validator;

trait ValidatesSingleActivePeriod
{
    protected function validateSingleActivePeriod(Validator $validator, ?int $ignorePeriodId = null): void
    {
        if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_active')) {
            return;
        }

        $activePeriod = AcademicPeriod::query()
            ->where('is_active', true)
            ->when($ignorePeriodId !== null, function ($query) use ($ignorePeriodId) {
                $query->where('id', '!=', $ignorePeriodId);
            })
            ->first();

        if (! $activePeriod) {
            return;
        }

        $periodLabel = $activePeriod->name
            ?? $activePeriod->code
            ?? "ID {$activePeriod->id}";

        $validator->errors()->add(
            'is_active',
            "Hanya satu academic period yang boleh aktif. Period {$periodLabel} masih aktif, nonaktifkan terlebih dahulu.",
        );
    }
}
