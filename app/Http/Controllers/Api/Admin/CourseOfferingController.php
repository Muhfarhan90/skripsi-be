<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourseOffering\StoreCourseOfferingRequest;
use App\Http\Requests\Admin\CourseOffering\UpdateCourseOfferingRequest;
use App\Http\Resources\AdminOfferingAssignmentSubmissionResource;
use App\Http\Resources\AdminOfferingEnrollmentResource;
use App\Http\Resources\CourseOfferingResource;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\EnrollmentService;
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
        $actor = $request->user();

        $offerings = $this->indexQuery()
            ->withCount('enrollments')
            ->when($this->isInstructor($actor), function ($query) use ($actor) {
                $query->whereHas('course', function ($courseQuery) use ($actor) {
                    $courseQuery->where('instructor_id', $actor->id);
                });
            })
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
        $payload = $request->validated();
        $course = Course::query()->findOrFail((int) $payload['course_id']);
        $this->assertCanManageCourse($course, $request->user());

        $offering = CourseOffering::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Course offering created successfully',
            'data' => new CourseOfferingResource($this->detailQuery()->findOrFail($offering->id)),
        ]);
    }

    public function show(Request $request, string $id)
    {
        $offering = $this->resolveManagedOffering((int) $id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Course offering retrieved successfully',
            'data' => new CourseOfferingResource($offering),
        ]);
    }

    public function update(UpdateCourseOfferingRequest $request, string $id)
    {
        $offering = $this->resolveManagedOffering((int) $id, $request->user());
        $payload = $request->validated();
        $courseId = (int) ($payload['course_id'] ?? $offering->course_id);
        $course = Course::query()->findOrFail($courseId);
        $this->assertCanManageCourse($course, $request->user());

        $offering->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Course offering updated successfully',
            'data' => new CourseOfferingResource($this->detailQuery()->findOrFail($offering->id)),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $offering = $this->resolveManagedOffering((int) $id, $request->user());
        $offering->loadCount(['enrollments', 'orderItems']);

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

    public function enrollments(Request $request, string $id, EnrollmentService $enrollmentService)
    {
        $offering = $this->resolveManagedOffering((int) $id, $request->user());
        $perPage = max((int) $request->query('per_page', 10), 1);
        $search = trim((string) $request->query('search', ''));
        $enrollments = $enrollmentService->getByCourseOfferingIdForAdmin($offering->id, $perPage, $search);

        return response()->json([
            'success' => true,
            'message' => 'Course offering enrollment list retrieved successfully',
            'data' => AdminOfferingEnrollmentResource::collection($enrollments),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    public function assignmentSubmissions(Request $request, string $id, AssignmentService $assignmentService)
    {
        $offering = $this->resolveManagedOffering((int) $id, $request->user());
        $submissions = $assignmentService->getSubmissionsByOfferingForAdmin($offering->id, [
            'assignment_id' => $request->query('assignment_id'),
            'status' => $request->query('status'),
            'search' => $request->query('search'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Course offering assignment submissions retrieved successfully',
            'data' => AdminOfferingAssignmentSubmissionResource::collection($submissions),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
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

    private function resolveManagedOffering(int $id, User $actor): CourseOffering
    {
        $offering = $this->detailQuery()->findOrFail($id);
        $this->assertCanManageOffering($offering, $actor);

        return $offering;
    }

    private function assertCanManageOffering(CourseOffering $offering, User $actor): void
    {
        $actor->loadMissing('role');
        $roleName = $actor->role?->name;

        if ($roleName === 'admin') {
            return;
        }

        $offering->loadMissing('course');
        if ($roleName === 'instructor' && (int) $offering->course?->instructor_id === (int) $actor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'course_offering' => ['You do not have permission to manage this course offering.'],
        ]);
    }

    private function assertCanManageCourse(Course $course, User $actor): void
    {
        $actor->loadMissing('role');
        $roleName = $actor->role?->name;

        if ($roleName === 'admin') {
            return;
        }

        if ($roleName === 'instructor' && (int) $course->instructor_id === (int) $actor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'course_offering' => ['You do not have permission to manage this course offering.'],
        ]);
    }

    private function isInstructor(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        $actor->loadMissing('role');

        return $actor->role?->name === 'instructor';
    }
}
