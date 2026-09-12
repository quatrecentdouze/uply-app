<?php

declare(strict_types=1);

use App\Organizations\Http\Controllers\MeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [MeController::class, 'show'])->name('me.show');

    Route::middleware('verified')->group(function (): void {
        Route::patch('me', [MeController::class, 'update'])->name('me.update');
    });
});
