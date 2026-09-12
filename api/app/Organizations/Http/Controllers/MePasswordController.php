<?php

declare(strict_types=1);

namespace App\Organizations\Http\Controllers;

use App\Organizations\Actions\ChangePassword;
use App\Organizations\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\Response;

final class MePasswordController
{
    public function update(UpdatePasswordRequest $request, ChangePassword $changePassword): Response
    {
        $changePassword($request->currentUser(), $request->newPassword());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
