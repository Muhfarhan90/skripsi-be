<?php

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\AcademicPeriodController;
use App\Http\Controllers\Api\Admin\AssignmentController as AdminAssignmentController;
use App\Http\Controllers\Api\Admin\CourseController;
use App\Http\Controllers\Api\Admin\CourseOfferingController;
use App\Http\Controllers\Api\Admin\EnrollmentController as AdminEnrollmentController;
use App\Http\Controllers\Api\Admin\LessonController;
use App\Http\Controllers\Api\Admin\LessonProgressController as AdminLessonProgressController;
use App\Http\Controllers\Api\Admin\OptionController;
use App\Http\Controllers\Api\Admin\QuizAttemptController as AdminQuizAttemptController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\QuestionController;
use App\Http\Controllers\Api\Admin\QuizController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\SectionController;
use App\Http\Controllers\Api\Admin\TransactionController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VoucherController;
use App\Http\Controllers\Api\Admin\ForumController as AdminForumController;
use App\Http\Controllers\Api\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CourseCatalogController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\ForumController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\LessonProgressController;
use App\Http\Controllers\Api\QuizAttemptController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    // Signed GET route for email verification (used by VerifyApiEmail notification)
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('auth.verify');
    Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail'])->name('auth.resend-verification-email');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::get('/courses', [CourseCatalogController::class, 'index']);
Route::get('/courses/{slug}', [CourseCatalogController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::delete('/cart/items/{courseId}', [CartController::class, 'removeItem']);
    Route::post('/cart/checkout', [CartController::class, 'checkout']);

    Route::get('/enrollments', [EnrollmentController::class, 'index']);
    Route::get('/enrollments/{id}', [EnrollmentController::class, 'show']);
    Route::get('/enrollments/{id}/curriculum', [EnrollmentController::class, 'curriculum']);
    Route::get('/enrollments/{id}/lessons/{lessonId}', [EnrollmentController::class, 'lessonDetail']);
    Route::get('/enrollments/{id}/progress-summary', [EnrollmentController::class, 'progressSummary']);
    Route::get('/enrollments/{id}/next-lesson', [EnrollmentController::class, 'nextLesson']);
    Route::post('/enrollments/{id}/complete', [EnrollmentController::class, 'complete']);

    Route::get('/enrollments/{enrollmentId}/assignments', [AssignmentController::class, 'index']);
    Route::get('/enrollments/{enrollmentId}/assignments/{assignmentId}', [AssignmentController::class, 'show']);
    Route::post('/enrollments/{enrollmentId}/assignments/{assignmentId}/submit', [AssignmentController::class, 'submit']);

    Route::get('/enrollments/{enrollmentId}/lesson-progress', [LessonProgressController::class, 'index']);
    Route::get('/enrollments/{enrollmentId}/lesson-progress/{lessonId}', [LessonProgressController::class, 'show']);
    Route::put('/enrollments/{enrollmentId}/lesson-progress/{lessonId}', [LessonProgressController::class, 'upsert']);

    Route::get('/enrollments/{enrollmentId}/quizzes/{quizId}/attempts', [QuizAttemptController::class, 'index']);
    Route::post('/enrollments/{enrollmentId}/quizzes/{quizId}/attempts', [QuizAttemptController::class, 'store']);
    Route::get('/enrollments/{enrollmentId}/quizzes/{quizId}/attempts/{attemptId}', [QuizAttemptController::class, 'show']);
    Route::put('/enrollments/{enrollmentId}/quizzes/{quizId}/attempts/{attemptId}/answers/{questionId}', [QuizAttemptController::class, 'upsertAnswer']);
    Route::post('/enrollments/{enrollmentId}/quizzes/{quizId}/attempts/{attemptId}/submit', [QuizAttemptController::class, 'submit']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::patch('/orders/{id}/payment-submission', [OrderController::class, 'submitPayment']);

    // Forum (Student) - harus enrolled
    Route::get('/courses/{courseId}/forum', [ForumController::class, 'index']);
    Route::post('/courses/{courseId}/forum', [ForumController::class, 'store']);
    Route::get('/courses/{courseId}/forum/{postId}', [ForumController::class, 'show']);
    Route::put('/courses/{courseId}/forum/{postId}', [ForumController::class, 'update']);
    Route::delete('/courses/{courseId}/forum/{postId}', [ForumController::class, 'destroy']);
    Route::post('/courses/{courseId}/forum/{postId}/replies', [ForumController::class, 'storeReply']);
    Route::put('/forum-replies/{replyId}', [ForumController::class, 'updateReply']);
    Route::delete('/forum-replies/{replyId}', [ForumController::class, 'destroyReply']);

    // Reviews
    Route::get('/courses/{courseId}/reviews', [ReviewController::class, 'index']);
    Route::post('/courses/{courseId}/reviews', [ReviewController::class, 'store']);
    Route::put('/courses/{courseId}/reviews/{reviewId}', [ReviewController::class, 'update']);
    Route::delete('/courses/{courseId}/reviews/{reviewId}', [ReviewController::class, 'destroy']);

    // Certificates
    Route::get('/certificates', [CertificateController::class, 'index']);
    Route::get('/enrollments/{enrollmentId}/certificate', [CertificateController::class, 'show']);
    Route::post('/enrollments/{enrollmentId}/certificate', [CertificateController::class, 'generate']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/courses/{courseId}/curriculum', [CourseController::class, 'curriculum']);
    Route::put('/courses/{courseId}/curriculum', [CourseController::class, 'upsertCurriculum']);
    Route::get('/courses/{courseId}/quizzes', [QuizController::class, 'indexByCourse']);
    Route::post('/courses/{courseId}/sections/{sectionId}/quizzes', [QuizController::class, 'storeForSection']);
    Route::put('/courses/{courseId}/sections/{sectionId}/quizzes/{quizId}', [QuizController::class, 'updateForSection']);

    Route::post('/quizzes/{quizId}/questions', [QuestionController::class, 'storeForQuiz']);
    Route::put('/quizzes/{quizId}/questions/reorder', [QuestionController::class, 'reorderForQuiz']);
    Route::put('/quizzes/{quizId}/questions/{questionId}', [QuestionController::class, 'updateForQuiz']);
    Route::delete('/quizzes/{quizId}/questions/{questionId}', [QuestionController::class, 'destroyForQuiz']);

    Route::post('/questions/{questionId}/options', [OptionController::class, 'storeForQuestion']);
    Route::put('/questions/{questionId}/options/{optionId}', [OptionController::class, 'updateForQuestion']);
    Route::delete('/questions/{questionId}/options/{optionId}', [OptionController::class, 'destroyForQuestion']);

    Route::get('/enrollments', [AdminEnrollmentController::class, 'index']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::post('/orders', [AdminOrderController::class, 'store']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);

    Route::get('/enrollments/{id}', [AdminEnrollmentController::class, 'show']);
    Route::get('/courses/{courseId}/enrollments', [AdminEnrollmentController::class, 'byCourse']);
    Route::patch('/enrollments/{id}/status', [AdminEnrollmentController::class, 'updateStatus']);
    Route::post('/enrollments/{id}/sync-progress', [AdminEnrollmentController::class, 'syncProgress']);

    Route::get('/courses/{courseId}/assignments', [AdminAssignmentController::class, 'indexByCourse']);
    Route::post('/courses/{courseId}/assignments', [AdminAssignmentController::class, 'storeForCourse']);
    Route::put('/courses/{courseId}/assignments/{assignmentId}', [AdminAssignmentController::class, 'updateForCourse']);
    Route::get('/assignments/{assignmentId}/submissions', [AdminAssignmentController::class, 'submissions']);
    Route::put('/assignment-submissions/{submissionId}/review', [AdminAssignmentController::class, 'reviewSubmission']);

    Route::get('/enrollments/{enrollmentId}/lesson-progress', [AdminLessonProgressController::class, 'index']);
    Route::get('/enrollments/{enrollmentId}/lesson-progress/{lessonId}', [AdminLessonProgressController::class, 'show']);
    Route::put('/enrollments/{enrollmentId}/lesson-progress/{lessonId}', [AdminLessonProgressController::class, 'upsert']);

    Route::get('/quizzes/{quizId}/attempts', [AdminQuizAttemptController::class, 'index']);
    Route::get('/quizzes/{quizId}/attempts/{attemptId}', [AdminQuizAttemptController::class, 'show']);
    Route::put('/quizzes/{quizId}/attempts/{attemptId}/answers/{questionId}/grade', [AdminQuizAttemptController::class, 'gradeAnswer']);

    // Forum (Admin & Instructor) - CRUD + moderasi
    Route::get('/courses/{courseId}/forum', [AdminForumController::class, 'index']);
    Route::post('/courses/{courseId}/forum', [AdminForumController::class, 'store']);
    Route::get('/courses/{courseId}/forum/{postId}', [AdminForumController::class, 'show']);
    Route::put('/courses/{courseId}/forum/{postId}', [AdminForumController::class, 'update']);
    Route::patch('/courses/{courseId}/forum/{postId}/pin', [AdminForumController::class, 'togglePin']);
    Route::delete('/courses/{courseId}/forum/{postId}', [AdminForumController::class, 'destroyPost']);
    Route::post('/courses/{courseId}/forum/{postId}/replies', [AdminForumController::class, 'storeReply']);
    Route::put('/forum-replies/{replyId}', [AdminForumController::class, 'updateReply']);
    Route::delete('/forum-replies/{replyId}', [AdminForumController::class, 'destroyReply']);

    // Reviews (Admin & Instructor) - Moderasi menghapus review
    Route::delete('/courses/{courseId}/reviews/{reviewId}', [AdminReviewController::class, 'destroy']);
});

Route::apiResource('admin/categories', CategoryController::class)->middleware(['auth:sanctum', 'admin']);
Route::apiResource('admin/courses', CourseController::class)->middleware(['auth:sanctum', 'admin']);
Route::apiResource('admin/sections', SectionController::class)->middleware(['auth:sanctum', 'admin']);
Route::apiResource('admin/lessons', LessonController::class)->middleware(['auth:sanctum', 'admin']);
Route::apiResource('admin/quizzes', QuizController::class)->middleware(['auth:sanctum', 'admin']);
Route::apiResource('admin/questions', QuestionController::class)->middleware(['auth:sanctum', 'admin']);
Route::apiResource('admin/options', OptionController::class)->middleware(['auth:sanctum', 'admin']);
Route::apiResource('admin/vouchers', VoucherController::class)->middleware(['auth:sanctum', 'admin']);
Route::apiResource('admin/transactions', TransactionController::class)->middleware(['auth:sanctum', 'admin']);
Route::apiResource('admin/users', UserController::class)->middleware(['auth:sanctum', 'admin']);
Route::apiResource('admin/roles', RoleController::class)->only(['index', 'show'])->middleware(['auth:sanctum', 'admin']);
Route::apiResource('admin/academic-periods', AcademicPeriodController::class)->only(['index', 'show'])->middleware(['auth:sanctum', 'admin']);
Route::apiResource('admin/course-offerings', CourseOfferingController::class)->only(['index', 'show'])->middleware(['auth:sanctum', 'admin']);
