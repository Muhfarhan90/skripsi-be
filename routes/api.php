<?php

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\CourseController;
use App\Http\Controllers\Api\Admin\LessonController;
use App\Http\Controllers\Api\Admin\OptionController;
use App\Http\Controllers\Api\Admin\QuestionController;
use App\Http\Controllers\Api\Admin\QuizController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\SectionController;
use App\Http\Controllers\Api\Admin\TransactionController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VoucherController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\LessonProgressController;
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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/enrollments', [EnrollmentController::class, 'index']);
    Route::post('/enrollments', [EnrollmentController::class, 'store']);
    Route::get('/enrollments/{id}', [EnrollmentController::class, 'show']);
    Route::post('/enrollments/{id}/complete', [EnrollmentController::class, 'complete']);
    Route::get('/courses/{courseId}/enrollment-status', [EnrollmentController::class, 'status']);

    Route::get('/lesson-progress', [LessonProgressController::class, 'index']);
    Route::post('/lesson-progress', [LessonProgressController::class, 'store']);
    Route::get('/lesson-progress/{id}', [LessonProgressController::class, 'show']);
});

Route::apiResource('admin/categories', CategoryController::class)->middleware('auth:sanctum');
Route::apiResource('admin/courses', CourseController::class)->middleware('auth:sanctum');
Route::apiResource('admin/sections', SectionController::class)->middleware('auth:sanctum');
Route::apiResource('admin/lessons', LessonController::class)->middleware('auth:sanctum');
Route::apiResource('admin/quizzes', QuizController::class)->middleware('auth:sanctum');
Route::apiResource('admin/questions', QuestionController::class)->middleware('auth:sanctum');
Route::apiResource('admin/options', OptionController::class)->middleware('auth:sanctum');
Route::apiResource('admin/vouchers', VoucherController::class)->middleware('auth:sanctum');
Route::apiResource('admin/transactions', TransactionController::class)->middleware('auth:sanctum');
Route::apiResource('admin/users', UserController::class)->middleware('auth:sanctum');
Route::apiResource('admin/roles', RoleController::class)->only(['index', 'show'])->middleware('auth:sanctum');
