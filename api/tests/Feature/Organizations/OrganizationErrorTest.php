<?php

declare(strict_types=1);

use App\Organizations\Enums\OrganizationError;

it('maps each organizations error to its status', function (OrganizationError $error, int $status): void {
    expect($error->httpStatus())->toBe($status)
        ->and($error->code())->toBe($error->value);
})->with([
    [OrganizationError::SoleOwner, 409],
    [OrganizationError::LastOwner, 409],
    [OrganizationError::OrganizationLimitReached, 409],
    [OrganizationError::RoleNotAllowed, 403],
    [OrganizationError::InvitationEmailMismatch, 403],
    [OrganizationError::InvitationExpired, 410],
    [OrganizationError::InvitationPending, 409],
    [OrganizationError::AlreadyMember, 409],
    [OrganizationError::InvitationLimitReached, 409],
]);

it('has a text for every organizations error', function (OrganizationError $error): void {
    expect(__($error->messageKey()))->toBeString()->not->toBe($error->messageKey());
})->with(OrganizationError::cases());
