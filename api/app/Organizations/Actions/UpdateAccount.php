<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Models\User;

final readonly class UpdateAccount
{
    public function __invoke(User $user, ?string $name, ?string $email): User
    {
        if ($name !== null) {
            $user->name = $name;
        }

        $changesEmail = $email !== null && $email !== $user->email;
        if ($changesEmail) {
            $user->email = $email;
            $user->email_verified_at = null;
        }

        $user->save();

        if ($changesEmail) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }
}
