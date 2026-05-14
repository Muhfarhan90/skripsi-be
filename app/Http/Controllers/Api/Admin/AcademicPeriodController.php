<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcademicPeriod\StoreAcademicPeriodRequest;
use App\Http\Requests\Admin\AcademicPeriod\UpdateAcademicPeriodRequest;
use App\Http\Resources\AcademicPeriodResource;
use App\Models\AcademicPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AcademicPeriodController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $isActive = $request->query('is_active');

        $periods = $this->indexQuery()
            ->when($isActive !== null && $isActive !== '' && $isActive !== 'all', function ($query) use ($isActive) {
                $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Academic periods retrieved successfully',
            'data' => AcademicPeriodResource::collection($periods),
        ]);
    }

    public function store(StoreAcademicPeriodRequest $request)
    {
        $period = AcademicPeriod::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Academic period created successfully',
            'data' => new AcademicPeriodResource($this->detailQuery()->findOrFail($period->id)),
        ]);
    }

    public function show(string $id)
    {
        $period = $this->detailQuery()->findOrFail((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Academic period retrieved successfully',
            'data' => new AcademicPeriodResource($period),
        ]);
    }

    public function update(UpdateAcademicPeriodRequest $request, string $id)
    {
        $period = AcademicPeriod::query()->findOrFail((int) $id);
        $period->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Academic period updated successfully',
            'data' => new AcademicPeriodResource($this->detailQuery()->findOrFail($period->id)),
        ]);
    }

    public function destroy(string $id)
    {
        $period = AcademicPeriod::query()
            ->withCount('courseOfferings')
            ->findOrFail((int) $id);

        if ((int) ($period->course_offerings_count ?? 0) > 0) {
            throw ValidationException::withMessages([
                'academic_period' => ['Academic period cannot be deleted while it still has course offerings.'],
            ]);
        }

        $period->delete();

        return response()->json([
            'success' => true,
            'message' => 'Academic period deleted successfully',
        ]);
    }

    private function indexQuery(): Builder
    {
        return AcademicPeriod::query()
            ->withCount('courseOfferings')
            ->orderByDesc('start_at')
            ->orderByDesc('id');
    }

    private function detailQuery(): Builder
    {
        return AcademicPeriod::query()
            ->withCount('courseOfferings')
            ->with([
                'courseOfferings' => function ($query) {
                    $query->withCount('enrollments')
                        ->with([
                            'course:id,title,slug,category_id',
                            'course.category:id,name',
                        ])
                        ->orderByDesc('id');
                },
            ]);
    }
}
