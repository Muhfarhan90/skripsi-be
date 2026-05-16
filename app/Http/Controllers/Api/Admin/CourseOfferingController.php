<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourseOffering\StoreCourseOfferingRequest;
use App\Http\Requests\Admin\CourseOffering\UpdateCourseOfferingRequest;
use App\Http\Resources\CourseOfferingResource;
use App\Models\AcademicPeriod;
use App\Models\CourseOffering;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CourseOfferingController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $isActive = $request->query('is_active');
        $periodId = (int) $request->query('academic_period_id', 0);
        $perPage = max((int) $request->query('per_page', 10), 1);

        $offerings = $this->indexQuery()
            ->withCount('enrollments')
            ->when($isActive !== null && $isActive !== '' && $isActive !== 'all', function ($query) use ($isActive) {
                $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
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
            ->orderByDesc(
                AcademicPeriod::query()
                    ->select('start_at')
                    ->whereColumn('academic_periods.id', 'course_offerings.academic_period_id')
                    ->limit(1)
            )
            ->orderByDesc('course_offerings.id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Course offerings retrieved successfully',
            'data' => CourseOfferingResource::collection($offerings),
            'meta' => [
                'current_page' => $offerings->currentPage(),
                'last_page' => $offerings->lastPage(),
                'per_page' => $offerings->perPage(),
                'total' => $offerings->total(),
            ],
        ]);
    }

    public function store(StoreCourseOfferingRequest $request)
    {
        $offering = CourseOffering::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Course offering created successfully',
            'data' => new CourseOfferingResource($this->detailQuery()->findOrFail($offering->id)),
        ]);
    }

    public function show(string $id)
    {
        $offering = $this->detailQuery()->findOrFail((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Course offering retrieved successfully',
            'data' => new CourseOfferingResource($offering),
        ]);
    }

    public function update(UpdateCourseOfferingRequest $request, string $id)
    {
        $offering = CourseOffering::query()->findOrFail((int) $id);
        $offering->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Course offering updated successfully',
            'data' => new CourseOfferingResource($this->detailQuery()->findOrFail($offering->id)),
        ]);
    }

    public function destroy(string $id)
    {
        $offering = CourseOffering::query()
            ->withCount(['enrollments', 'orderItems'])
            ->findOrFail((int) $id);

        if ((int) ($offering->enrollments_count ?? 0) > 0 || (int) ($offering->order_items_count ?? 0) > 0) {
            throw ValidationException::withMessages([
                'course_offering' => ['Course offering cannot be deleted because it is already referenced by enrollments or orders.'],
            ]);
        }

        $offering->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course offering deleted successfully',
        ]);
    }

    private function indexQuery(): Builder
    {
        return CourseOffering::query()
            ->with([
                'course:id,title,slug,category_id,instructor_id',
                'course.category:id,name',
                'course.instructor:id,fullname',
                'academicPeriod:id,code,name,start_at,end_at,enrollment_open_at,enrollment_close_at,is_active',
            ]);
    }

    private function detailQuery(): Builder
    {
        return CourseOffering::query()
            ->with([
                'course:id,title,slug,category_id,instructor_id',
                'course.category:id,name',
                'course.instructor:id,fullname',
                'academicPeriod:id,code,name,start_at,end_at,enrollment_open_at,enrollment_close_at,is_active',
            ])
            ->withCount('enrollments');
    }
}
