<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Models\User;
use SensitiveParameter;

final readonly class ChangePassword
{
    public function __invoke(User $user, #[SensitiveParameter] string $password): void
    {
        $user->password = $password;
        $user->save();
    }
}
