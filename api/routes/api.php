<?php

declare(strict_types=1);

use App\Organizations\Http\Controllers\InvitationController;
use App\Organizations\Http\Controllers\MeController;
use App\Organizations\Http\Controllers\MemberController;
use App\Organizations\Http\Controllers\MePasswordController;
use App\Organizations\Http\Controllers\OrganizationController;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Membership;
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

        Route::scopeBindings()->group(function (): void {
            Route::get('organizations/{organization}/members', [MemberController::class, 'index'])
                ->can('viewAny', [Membership::class, 'organization'])
                ->name('members.index');
            Route::patch('organizations/{organization}/members/{membership}', [MemberController::class, 'update'])
                ->can('update', 'membership')
                ->name('members.update');
            Route::delete('organizations/{organization}/members/{membership}', [MemberController::class, 'destroy'])
                ->can('delete', 'membership')
                ->name('members.destroy');

            Route::get('organizations/{organization}/invitations', [InvitationController::class, 'index'])
                ->can('viewAny', [Invitation::class, 'organization'])
                ->name('invitations.index');
            Route::post('organizations/{organization}/invitations', [InvitationController::class, 'store'])
                ->can('create', [Invitation::class, 'organization'])
                ->name('invitations.store');
            Route::delete('organizations/{organization}/invitations/{invitation}', [InvitationController::class, 'destroy'])
                ->can('delete', 'invitation')
                ->name('invitations.destroy');
        });
    });
});
