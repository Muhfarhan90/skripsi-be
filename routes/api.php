<?php

use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\CourseController;
use App\Http\Controllers\Api\Admin\LessonController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\SectionController;
use App\Http\Controllers\Api\Admin\UserController;
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

Route::apiResource('admin/categories', CategoryController::class)->middleware('auth:sanctum');
Route::apiResource('admin/courses', CourseController::class)->middleware('auth:sanctum');
Route::apiResource('admin/sections', SectionController::class)->middleware('auth:sanctum');
Route::apiResource('admin/lessons', LessonController::class)->middleware('auth:sanctum');
Route::apiResource('admin/users', UserController::class)->middleware('auth:sanctum');
Route::apiResource('admin/roles', RoleController::class)->only(['index', 'show'])->middleware('auth:sanctum');
