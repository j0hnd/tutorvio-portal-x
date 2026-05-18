<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });
    
    // Add your v1 routes here
    // Route::prefix('auth')->group(function () { ... });
    // Route::apiResource('users', UserController::class);
    // Route::apiResource('students', StudentController::class);
    // Route::apiResource('tutors', TutorController::class);
    // Route::apiResource('lessons', LessonController::class);
    // Route::apiResource('bookings', BookingController::class);
    // Route::apiResource('payments', PaymentController::class);
});
