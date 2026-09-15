<?php

declare(strict_types=1);

namespace App\Organizations\Values;

use App\Organizations\Models\Invitation;
use SensitiveParameter;

final readonly class IssuedInvitation
{
    public function __construct(
        public Invitation $invitation,
        #[SensitiveParameter] public string $token,
    ) {}
}
