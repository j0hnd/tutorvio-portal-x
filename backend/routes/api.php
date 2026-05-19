<?php

use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\InvitationController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('login', [LoginController::class, 'login']);
        Route::post('register', [RegisterController::class, 'register']);
        Route::post('forgot-password', [ForgotPasswordController::class, 'forgotPassword']);
        Route::post('reset-password', [ResetPasswordController::class, 'resetPassword']);
        Route::get('accept-invitation/{token}', [InvitationController::class, 'accept']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [LoginController::class, 'logout']);
            Route::post('invite', [InvitationController::class, 'invite'])
                ->middleware(['role:admin|staff', 'permission:users.create']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        Route::prefix('admin')->middleware(['role:admin', 'permission:admin.access'])->group(function () {
            Route::get('/access', function () {
                return response()->json(['status' => 'ok']);
            });
        });

        Route::prefix('admin')->middleware(['role:admin|staff'])->group(function () {
            Route::get('/users', function () {
                return response()->json(['status' => 'ok']);
            })->middleware('permission:users.view');
        });

        Route::prefix('users')->middleware('role:admin|staff')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])
                ->middleware('permission:users.view');
            Route::post('/', [UserManagementController::class, 'store'])
                ->middleware('permission:users.create');
            Route::get('/{user}', [UserManagementController::class, 'show'])
                ->middleware('permission:users.view');
            Route::match(['put', 'patch'], '/{user}', [UserManagementController::class, 'update'])
                ->middleware('permission:users.update');
            Route::post('/{user}/activate', [UserManagementController::class, 'activate'])
                ->middleware('permission:users.activate');
            Route::post('/{user}/deactivate', [UserManagementController::class, 'deactivate'])
                ->middleware('permission:users.deactivate');
            Route::post('/{user}/roles', [UserManagementController::class, 'syncRoles'])
                ->middleware('permission:users.assign_roles');
            Route::get('/{user}/status-history', [UserManagementController::class, 'statusHistory'])
                ->middleware('permission:users.view');
        });
    });
});
