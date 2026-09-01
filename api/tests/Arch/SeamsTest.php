<?php

declare(strict_types=1);

arch('php preset')->preset()->php();

arch('security preset')->preset()->security();

arch('strict types everywhere')
    ->expect(['App', 'Database\Factories', 'Database\Seeders', 'Tests'])
    ->toUseStrictTypes();
