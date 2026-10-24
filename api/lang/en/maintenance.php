<?php

declare(strict_types=1);

return [
    'errors' => [
        'maintenance_started' => 'This maintenance window has already started, so its start can no longer change and it cannot be deleted.',
        'maintenance_ended' => 'This maintenance window has ended and can no longer change.',
        'maintenance_not_active' => 'Only an active maintenance window can be ended.',
        'maintenance_limit_reached' => 'This organization already has the most upcoming or active maintenance windows allowed (:max).',
    ],
    'validation' => [
        'monitor' => 'This monitor does not belong to the organization.',
        'start_in_past' => 'The start cannot be more than :seconds seconds in the past.',
        'start_too_far' => 'The start must be at most :days days ahead.',
        'end_not_after_start' => 'The end must come after the start.',
        'end_in_past' => 'The end must be in the future.',
        'too_long' => 'A maintenance window lasts at most :hours hours.',
    ],
];
