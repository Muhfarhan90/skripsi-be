<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AcademicPeriodResource;
use App\Models\AcademicPeriod;
use Illuminate\Http\Request;

class AcademicPeriodController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = strtolower(trim((string) $request->query('status', '')));

        $periods = AcademicPeriod::query()
            ->withCount('courseOfferings')
            ->when($status !== '' && $status !== 'all', function ($query) use ($status) {
                $query->whereRaw('LOWER(status) = ?', [$status]);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Academic periods retrieved successfully',
            'data' => AcademicPeriodResource::collection($periods),
        ]);
    }

    public function show(string $id)
    {
        $period = AcademicPeriod::query()
            ->withCount('courseOfferings')
            ->with([
                'courseOfferings.course:id,title,slug,category_id',
                'courseOfferings.course.category:id,name',
            ])
            ->findOrFail((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Academic period retrieved successfully',
            'data' => new AcademicPeriodResource($period),
        ]);
    }
}
