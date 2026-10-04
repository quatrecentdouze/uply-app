export type Window = '24h' | '7d' | '30d';

export interface MonitorListFilters {
  state?: 'up' | 'down' | 'pending' | 'paused';
  search?: string;
  page?: number;
  perPage?: number;
}

export interface IncidentListFilters {
  state?: 'open' | 'resolved';
  monitor?: string;
  page?: number;
  perPage?: number;
}

const organizationScope = (organizationId: string) => ['organizations', organizationId] as const;

export const queryKeys = {
  meta: () => ['meta'] as const,
  me: () => ['me'] as const,
  organizations: () => ['organization-list'] as const,
  invitation: (token: string) => ['invitations', token] as const,
  organization: (organizationId: string) => organizationScope(organizationId),
  monitors: {
    all: (organizationId: string) => [...organizationScope(organizationId), 'monitors'] as const,
    lists: (organizationId: string) => [...queryKeys.monitors.all(organizationId), 'list'] as const,
    list: (organizationId: string, filters: MonitorListFilters) => [...queryKeys.monitors.lists(organizationId), filters] as const,
    detail: (organizationId: string, monitorId: string) => [...queryKeys.monitors.all(organizationId), 'detail', monitorId] as const,
    stats: (organizationId: string, monitorId: string) => [...queryKeys.monitors.all(organizationId), 'stats', monitorId] as const,
    statsWindow: (organizationId: string, monitorId: string, window: Window) => [...queryKeys.monitors.stats(organizationId, monitorId), window] as const,
    checks: (organizationId: string, monitorId: string) => [...queryKeys.monitors.all(organizationId), 'checks', monitorId] as const,
  },
  overview: {
    all: (organizationId: string) => [...organizationScope(organizationId), 'overview'] as const,
    window: (organizationId: string, window: Window) => [...queryKeys.overview.all(organizationId), window] as const,
  },
  incidents: {
    all: (organizationId: string) => [...organizationScope(organizationId), 'incidents'] as const,
    lists: (organizationId: string) => [...queryKeys.incidents.all(organizationId), 'list'] as const,
    list: (organizationId: string, filters: IncidentListFilters) => [...queryKeys.incidents.lists(organizationId), filters] as const,
    detail: (organizationId: string, incidentId: string) => [...queryKeys.incidents.all(organizationId), 'detail', incidentId] as const,
  },
  alertChannels: {
    all: (organizationId: string) => [...organizationScope(organizationId), 'alert-channels'] as const,
    list: (organizationId: string) => [...queryKeys.alertChannels.all(organizationId), 'list'] as const,
    ofMonitor: (organizationId: string, monitorId: string) => [...queryKeys.alertChannels.all(organizationId), 'monitor', monitorId] as const,
  },
  statusPages: {
    all: (organizationId: string) => [...organizationScope(organizationId), 'status-pages'] as const,
    list: (organizationId: string) => [...queryKeys.statusPages.all(organizationId), 'list'] as const,
    detail: (organizationId: string, statusPageId: string) => [...queryKeys.statusPages.all(organizationId), 'detail', statusPageId] as const,
  },
  members: (organizationId: string) => [...organizationScope(organizationId), 'members'] as const,
  invitations: (organizationId: string) => [...organizationScope(organizationId), 'invitations'] as const,
} as const;
