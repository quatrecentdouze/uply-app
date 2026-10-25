<?php

declare(strict_types=1);

use App\Alerting\Http\Controllers\AlertChannelController;
use App\Alerting\Http\Controllers\AlertChannelTestController;
use App\Alerting\Http\Controllers\MonitorChannelController;
use App\Alerting\Models\AlertChannel;
use App\Incidents\Http\Controllers\IncidentController;
use App\Incidents\Models\Incident;
use App\Maintenance\Http\Controllers\MaintenanceWindowController;
use App\Maintenance\Models\MaintenanceWindow;
use App\Monitoring\Http\Controllers\CheckResultController;
use App\Monitoring\Http\Controllers\MonitorController;
use App\Monitoring\Http\Controllers\MonitorStatsController;
use App\Monitoring\Http\Controllers\OverviewController;
use App\Monitoring\Models\Monitor;
use App\Organizations\Http\Controllers\InvitationController;
use App\Organizations\Http\Controllers\InvitationTokenController;
use App\Organizations\Http\Controllers\MeController;
use App\Organizations\Http\Controllers\MemberController;
use App\Organizations\Http\Controllers\MePasswordController;
use App\Organizations\Http\Controllers\OrganizationController;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Membership;
use App\Providers\StatusPagesServiceProvider;
use App\Shared\Http\MetaController;
use App\StatusPages\Http\Controllers\PublicStatusPageController;
use App\StatusPages\Http\Controllers\StatusPageController;
use App\StatusPages\Models\StatusPage;
use Illuminate\Support\Facades\Route;

Route::get('meta', MetaController::class)->name('meta');

Route::get('public/status-pages/{slug}', [PublicStatusPageController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware([
        'throttle:'.StatusPagesServiceProvider::PUBLIC_LIMITER,
        'cache.headers:public;max_age='.config()->integer('status_pages.public.cache_seconds'),
    ])
    ->name('status-pages.public.show');

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
            Route::get('organizations/{organization}/monitors/{monitor}/stats', [MonitorStatsController::class, 'show'])
                ->can('view', [Monitor::class, 'organization'])
                ->name('monitors.stats.show');
            Route::get('organizations/{organization}/overview', [OverviewController::class, 'show'])
                ->can('view', [Monitor::class, 'organization'])
                ->name('overview.show');
        });

        Route::whereUlid(['organization', 'incident'])->group(function (): void {
            Route::get('organizations/{organization}/incidents', [IncidentController::class, 'index'])
                ->can('view', [Incident::class, 'organization'])
                ->name('incidents.index');
            Route::get('organizations/{organization}/incidents/{incident}', [IncidentController::class, 'show'])
                ->can('view', [Incident::class, 'organization'])
                ->name('incidents.show');
        });

        Route::whereUlid(['organization', 'channel'])->group(function (): void {
            Route::get('organizations/{organization}/alert-channels', [AlertChannelController::class, 'index'])
                ->can('view', [AlertChannel::class, 'organization'])
                ->name('alert-channels.index');
            Route::post('organizations/{organization}/alert-channels', [AlertChannelController::class, 'store'])
                ->can('manage', [AlertChannel::class, 'organization'])
                ->name('alert-channels.store');
            Route::get('organizations/{organization}/alert-channels/{channel}', [AlertChannelController::class, 'show'])
                ->can('view', [AlertChannel::class, 'organization'])
                ->name('alert-channels.show');
            Route::patch('organizations/{organization}/alert-channels/{channel}', [AlertChannelController::class, 'update'])
                ->can('manage', [AlertChannel::class, 'organization'])
                ->name('alert-channels.update');
            Route::delete('organizations/{organization}/alert-channels/{channel}', [AlertChannelController::class, 'destroy'])
                ->can('manage', [AlertChannel::class, 'organization'])
                ->name('alert-channels.destroy');
            Route::post('organizations/{organization}/alert-channels/{channel}/test', [AlertChannelTestController::class, 'store'])
                ->can('manage', [AlertChannel::class, 'organization'])
                ->name('alert-channels.test');
        });

        Route::whereUlid(['organization', 'monitor'])->group(function (): void {
            Route::get('organizations/{organization}/monitors/{monitor}/alert-channels', [MonitorChannelController::class, 'index'])
                ->can('viewLinks', [AlertChannel::class, 'organization'])
                ->name('monitors.alert-channels.index');
            Route::put('organizations/{organization}/monitors/{monitor}/alert-channels', [MonitorChannelController::class, 'update'])
                ->can('manageLinks', [AlertChannel::class, 'organization'])
                ->name('monitors.alert-channels.update');
        });

        Route::whereUlid(['organization', 'maintenanceWindow'])->group(function (): void {
            Route::get('organizations/{organization}/maintenance-windows', [MaintenanceWindowController::class, 'index'])
                ->can('view', [MaintenanceWindow::class, 'organization'])
                ->name('maintenance-windows.index');
            Route::post('organizations/{organization}/maintenance-windows', [MaintenanceWindowController::class, 'store'])
                ->can('manage', [MaintenanceWindow::class, 'organization'])
                ->name('maintenance-windows.store');
            Route::get('organizations/{organization}/maintenance-windows/{maintenanceWindow}', [MaintenanceWindowController::class, 'show'])
                ->can('view', [MaintenanceWindow::class, 'organization'])
                ->name('maintenance-windows.show');
        });

        Route::whereUlid(['organization', 'statusPage'])->group(function (): void {
            Route::get('organizations/{organization}/status-pages', [StatusPageController::class, 'index'])
                ->can('view', [StatusPage::class, 'organization'])
                ->name('status-pages.index');
            Route::post('organizations/{organization}/status-pages', [StatusPageController::class, 'store'])
                ->can('manage', [StatusPage::class, 'organization'])
                ->name('status-pages.store');
            Route::get('organizations/{organization}/status-pages/{statusPage}', [StatusPageController::class, 'show'])
                ->can('view', [StatusPage::class, 'organization'])
                ->name('status-pages.show');
            Route::patch('organizations/{organization}/status-pages/{statusPage}', [StatusPageController::class, 'update'])
                ->can('manage', [StatusPage::class, 'organization'])
                ->name('status-pages.update');
            Route::delete('organizations/{organization}/status-pages/{statusPage}', [StatusPageController::class, 'destroy'])
                ->can('manage', [StatusPage::class, 'organization'])
                ->name('status-pages.destroy');
        });
    });
});
