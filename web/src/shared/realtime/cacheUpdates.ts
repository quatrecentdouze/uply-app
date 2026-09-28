import type { QueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/shared/api/queryKeys';
import type { Monitor, MonitorListResponse, MonitorResponse } from '@/shared/api/resources';
import type { RealtimeEvent } from '@/shared/realtime/events';

type MonitorPatch = Partial<Pick<Monitor, 'state' | 'state_changed_at' | 'last_checked_at' | 'last_response_ms'>>;

export function patchMonitorList(list: MonitorListResponse | undefined, monitorId: string, patch: MonitorPatch): MonitorListResponse | undefined {
  if (list === undefined || !list.data.some((monitor) => monitor.id === monitorId)) {
    return list;
  }
  return { ...list, data: list.data.map((monitor) => (monitor.id === monitorId ? { ...monitor, ...patch } : monitor)) };
}

export function patchMonitorDetail(detail: MonitorResponse | undefined, patch: MonitorPatch): MonitorResponse | undefined {
  return detail === undefined ? undefined : { ...detail, data: { ...detail.data, ...patch } };
}

function patchMonitor(queryClient: QueryClient, organizationId: string, monitorId: string, patch: MonitorPatch): void {
  queryClient.setQueriesData<MonitorListResponse>({ queryKey: queryKeys.monitors.lists(organizationId) }, (list) => patchMonitorList(list, monitorId, patch));
  queryClient.setQueryData<MonitorResponse>(queryKeys.monitors.detail(organizationId, monitorId), (detail) => patchMonitorDetail(detail, patch));
}

export function applyRealtimeEvent(queryClient: QueryClient, organizationId: string, event: RealtimeEvent): void {
  switch (event.name) {
    case 'monitor.checked':
      patchMonitor(queryClient, organizationId, event.payload.monitor_id, {
        state: event.payload.state,
        last_checked_at: event.payload.checked_at,
        last_response_ms: event.payload.response_ms,
      });
      return;
    case 'monitor.state-changed':
      patchMonitor(queryClient, organizationId, event.payload.monitor_id, {
        state: event.payload.state,
        state_changed_at: event.payload.changed_at,
      });
      void queryClient.invalidateQueries({ queryKey: queryKeys.monitors.lists(organizationId) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.monitors.stats(organizationId, event.payload.monitor_id) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.overview.all(organizationId) });
      return;
    case 'monitor.changed':
      if (event.payload.change === 'deleted') {
        queryClient.removeQueries({ queryKey: queryKeys.monitors.detail(organizationId, event.payload.monitor_id) });
      }
      void queryClient.invalidateQueries({ queryKey: queryKeys.monitors.all(organizationId) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.overview.all(organizationId) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.incidents.all(organizationId) });
      return;
    case 'incident.opened':
    case 'incident.resolved':
      void queryClient.invalidateQueries({ queryKey: queryKeys.incidents.all(organizationId) });
      return;
  }
}
