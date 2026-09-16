<?php

declare(strict_types=1);

namespace App\Realtime\Broadcasts;

use Illuminate\Broadcasting\PrivateChannel;

final class OrganizationChannel
{
    public const string PATTERN = self::PREFIX.'{organizationId}';

    private const string PREFIX = 'organization.';

    public static function of(string $organizationId): PrivateChannel
    {
        return new PrivateChannel(self::PREFIX.$organizationId);
    }
}
