<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\OnboardingStepController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    Route::get('/admin-only', fn () => ['message' => 'Welcome, admin'])
        ->middleware('role:admin');

    // الإشعارات
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    // سجل التدقيق: الأدمن بس
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('role:admin');

    // الأقسام: الكل يشوف، الأدمن بس يعدّل
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/departments/{department}', [DepartmentController::class, 'show']);

    Route::middleware('role:admin')->group(function () {
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::put('/departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
    });

    // الموظفين
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{employee}', [EmployeeController::class, 'show']);

    Route::middleware('role:admin,hr')->group(function () {
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::post('/employees/{employee}/onboarding', [OnboardingController::class, 'start']);
    });

    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
        ->middleware('role:admin');

    // الـOnboarding
    Route::get('/onboardings/{onboarding}', [OnboardingController::class, 'show']);

    Route::post('/onboardings/{onboarding}/submit', [OnboardingController::class, 'submit'])
        ->middleware('role:employee');

    Route::post('/onboardings/{onboarding}/hr-decision', [OnboardingController::class, 'hrDecision'])
        ->middleware('role:admin,hr');

    Route::post('/tasks/{task}/complete', [OnboardingStepController::class, 'completeTask'])
        ->middleware('role:admin,it');

    Route::post('/onboardings/{onboarding}/manager-decision', [OnboardingStepController::class, 'managerDecision'])
        ->middleware('role:admin,manager');

    // الملفات
    Route::get('/onboardings/{onboarding}/documents', [DocumentController::class, 'index']);
    Route::post('/onboardings/{onboarding}/documents', [DocumentController::class, 'store']);
    Route::patch('/documents/{document}/review', [DocumentController::class, 'review'])
        ->middleware('role:admin,hr');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);

        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // قائمة المدراء لنافذة إضافة موظف
    Route::get('/managers', fn () => \App\Models\User::whereHas('role', fn ($q) => $q->where('name', 'manager'))
        ->where('is_active', true)
        ->get(['id', 'name']))
        ->middleware('role:admin,hr');
});