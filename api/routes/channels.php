<?php

declare(strict_types=1);

use App\Realtime\Broadcasts\OrganizationChannel;
use App\Realtime\Channels\OrganizationMembersChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(OrganizationChannel::PATTERN, OrganizationMembersChannel::class);
