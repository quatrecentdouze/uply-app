<?php

declare(strict_types=1);

namespace App\StatusPages\Actions;

use App\StatusPages\Models\StatusPage;

final class DeleteStatusPage
{
    public function __invoke(StatusPage $page): void
    {
        $page->delete();
    }
}
