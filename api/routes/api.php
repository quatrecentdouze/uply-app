<?php

declare(strict_types=1);

use App\Organizations\Http\Controllers\MeController;
use App\Organizations\Http\Controllers\MePasswordController;
use App\Organizations\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [MeController::class, 'show'])->name('me.show');
    Route::delete('me', [MeController::class, 'destroy'])->name('me.destroy');

    Route::middleware('verified')->group(function (): void {
        Route::patch('me', [MeController::class, 'update'])->name('me.update');
        Route::put('me/password', [MePasswordController::class, 'update'])->name('me.password.update');

        Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
        Route::get('organizations/{organization}', [OrganizationController::class, 'show'])
            ->can('view', 'organization')
            ->name('organizations.show');
        Route::patch('organizations/{organization}', [OrganizationController::class, 'update'])
            ->can('update', 'organization')
            ->name('organizations.update');
        Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy'])
            ->can('delete', 'organization')
            ->name('organizations.destroy');
    });
});
