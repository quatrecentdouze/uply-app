<?php

declare(strict_types=1);

use App\Organizations\Enums\OrganizationError;
use App\Shared\Errors\DomainError;
use Illuminate\Support\Facades\Route;

it('answers a domain error with its text and its stable code', function (): void {
    Route::middleware('api')->get('/api/v1/domain-error-probe', fn () => throw new DomainError(OrganizationError::OrganizationLimitReached, ['max' => 10]));

    $this->getJson('/api/v1/domain-error-probe')
        ->assertConflict()
        ->assertExactJson([
            'message' => __('organizations.errors.organization_limit_reached', ['max' => 10]),
            'code' => 'organization_limit_reached',
        ]);
});

it('answers a domain error in json without an accept header', function (): void {
    Route::middleware('api')->get('/api/v1/domain-error-probe', fn () => throw new DomainError(OrganizationError::InvitationExpired));

    $this->get('/api/v1/domain-error-probe')
        ->assertStatus(410)
        ->assertJsonPath('code', 'invitation_expired');
});
