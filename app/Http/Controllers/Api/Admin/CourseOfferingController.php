<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseOfferingResource;
use App\Models\CourseOffering;
use Illuminate\Http\Request;

class CourseOfferingController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = strtolower(trim((string) $request->query('status', '')));
        $periodId = (int) $request->query('academic_period_id', 0);

        $offerings = CourseOffering::query()
            ->with([
                'course:id,title,slug,category_id',
                'course.category:id,name',
                'academicPeriod:id,code,name,start_at,end_at,enrollment_open_at,enrollment_close_at,status',
            ])
            ->withCount('enrollments')
            ->when($status !== '' && $status !== 'all', function ($query) use ($status) {
                $query->whereRaw('LOWER(status) = ?', [$status]);
            })
            ->when($periodId > 0, function ($query) use ($periodId) {
                $query->where('academic_period_id', $periodId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('title', 'like', "%{$search}%")
                        ->orWhereHas('course', function ($courseQuery) use ($search) {
                            $courseQuery->where('title', 'like', "%{$search}%");
                        })
                        ->orWhereHas('academicPeriod', function ($periodQuery) use ($search) {
                            $periodQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Course offerings retrieved successfully',
            'data' => CourseOfferingResource::collection($offerings),
        ]);
    }

    public function show(string $id)
    {
        $offering = CourseOffering::query()
            ->with([
                'course:id,title,slug,category_id,instructor_id',
                'course.category:id,name',
                'course.instructor:id,fullname',
                'academicPeriod:id,code,name,start_at,end_at,enrollment_open_at,enrollment_close_at,status',
            ])
            ->withCount('enrollments')
            ->findOrFail((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Course offering retrieved successfully',
            'data' => new CourseOfferingResource($offering),
        ]);
    }
}
