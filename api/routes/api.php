<?php

declare(strict_types=1);

use App\Monitoring\Http\Controllers\CheckResultController;
use App\Monitoring\Http\Controllers\MonitorController;
use App\Monitoring\Models\Monitor;
use App\Organizations\Http\Controllers\InvitationController;
use App\Organizations\Http\Controllers\InvitationTokenController;
use App\Organizations\Http\Controllers\MeController;
use App\Organizations\Http\Controllers\MemberController;
use App\Organizations\Http\Controllers\MePasswordController;
use App\Organizations\Http\Controllers\OrganizationController;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Membership;
use App\Shared\Http\MetaController;
use Illuminate\Support\Facades\Route;

Route::get('meta', MetaController::class)->name('meta');

Route::get('invitations/{token}', [InvitationTokenController::class, 'show'])
    ->where('token', InvitationTokenController::TOKEN_PATTERN)
    ->name('invitations.public.show');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [MeController::class, 'show'])->name('me.show');
    Route::delete('me', [MeController::class, 'destroy'])->name('me.destroy');

    Route::middleware('verified')->group(function (): void {
        Route::patch('me', [MeController::class, 'update'])->name('me.update');
        Route::put('me/password', [MePasswordController::class, 'update'])->name('me.password.update');

        Route::post('invitations/{token}/accept', [InvitationTokenController::class, 'accept'])
            ->where('token', InvitationTokenController::TOKEN_PATTERN)
            ->name('invitations.accept');

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

        Route::whereUlid(['organization', 'monitor'])->group(function (): void {
            Route::get('organizations/{organization}/monitors', [MonitorController::class, 'index'])
                ->can('view', [Monitor::class, 'organization'])
                ->name('monitors.index');
            Route::post('organizations/{organization}/monitors', [MonitorController::class, 'store'])
                ->can('manage', [Monitor::class, 'organization'])
                ->name('monitors.store');
            Route::get('organizations/{organization}/monitors/{monitor}', [MonitorController::class, 'show'])
                ->can('view', [Monitor::class, 'organization'])
                ->name('monitors.show');
            Route::patch('organizations/{organization}/monitors/{monitor}', [MonitorController::class, 'update'])
                ->can('manage', [Monitor::class, 'organization'])
                ->name('monitors.update');
            Route::delete('organizations/{organization}/monitors/{monitor}', [MonitorController::class, 'destroy'])
                ->can('manage', [Monitor::class, 'organization'])
                ->name('monitors.destroy');
            Route::get('organizations/{organization}/monitors/{monitor}/checks', [CheckResultController::class, 'index'])
                ->can('view', [Monitor::class, 'organization'])
                ->name('monitors.checks.index');
        });
    });
});
