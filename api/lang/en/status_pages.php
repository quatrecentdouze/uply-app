<?php

declare(strict_types=1);

return [
    'errors' => [
        'status_page_limit_reached' => 'This organization already has the most status pages allowed (:max).',
        'slug_taken' => 'Another status page already uses this slug.',
    ],
    'validation' => [
        'slug' => 'The slug uses lowercase letters, digits and dashes only.',
        'slug_taken' => 'Another status page already uses this slug.',
        'monitor' => 'This monitor does not belong to the organization.',
    ],
];
