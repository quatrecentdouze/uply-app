<?php

declare(strict_types=1);

namespace App\Organizations\Contracts;

enum Ability: string
{
    case MonitorsView = 'monitors.view';
    case IncidentsView = 'incidents.view';
    case StatusPagesView = 'status-pages.view';
    case AlertChannelsView = 'alert-channels.view';
    case MonitorsManage = 'monitors.manage';
    case StatusPagesManage = 'status-pages.manage';
    case AlertChannelsManage = 'alert-channels.manage';
    case MembersManage = 'members.manage';
    case OrganizationManage = 'organization.manage';
    case OrganizationDelete = 'organization.delete';
}
