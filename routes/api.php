<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\OnboardingStepController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/admin-only', fn () => ['message' => 'Welcome, admin'])
        ->middleware('role:admin');

    // الأقسام: الكل يشوف، الأدمن بس يعدّل
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/departments/{department}', [DepartmentController::class, 'show']);

    Route::middleware('role:admin')->group(function () {
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::put('/departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
    });

    // الموظفين: الكل يشوف حسب صلاحيته
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{employee}', [EmployeeController::class, 'show']);

    // HR والأدمن: إضافة وتعديل وبدء الـOnboarding
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

    // IT يكمل المهام
    Route::post('/tasks/{task}/complete', [OnboardingStepController::class, 'completeTask'])
        ->middleware('role:admin,it');

    // المدير يعتمد
    Route::post('/onboardings/{onboarding}/manager-decision', [OnboardingStepController::class, 'managerDecision'])
        ->middleware('role:admin,manager');
});