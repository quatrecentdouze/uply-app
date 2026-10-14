import { useState } from 'react';
import { useIncidents } from '@/features/incidents/api/incidents';
import { RecentIncidentsCard } from '@/features/incidents/components/RecentIncidentsCard';
import { useMonitorList, useOverview } from '@/features/monitors/api/monitors';
import { MonitorPanel } from '@/features/monitors/components/MonitorPanel';
import { MonitorStatesCard } from '@/features/monitors/components/MonitorStatesCard';
import { ResponseTimeCard } from '@/features/monitors/components/ResponseTimeCard';
import { UptimeCard } from '@/features/monitors/components/UptimeCard';
import { dashboardSettings } from '@/features/monitors/model/settings';
import { useCurrentOrganization } from '@/features/organizations/hooks/currentOrganization';
import type { Window } from '@/shared/api/queryKeys';
import { useNow } from '@/shared/time/useNow';
import { retryOf } from '@/shared/api/retry';

export function DashboardPage() {
  const { organization } = useCurrentOrganization();
  const [uptimeWindow, setUptimeWindow] = useState<Window>(dashboardSettings.default_window);
  const [responseWindow, setResponseWindow] = useState<Window>(dashboardSettings.default_window);
  const uptime = useOverview(organization.id, uptimeWindow);
  const response = useOverview(organization.id, responseWindow);
  const monitors = useMonitorList(organization.id, { perPage: dashboardSettings.list_size });
  const incidents = useIncidents(organization.id, { perPage: dashboardSettings.recent_incidents });
  const now = useNow(dashboardSettings.clock_tick_seconds);

  return (
    <div>
      <div className="mb-4 grid gap-4 sm:mb-5 sm:grid-cols-2 sm:gap-[18px] lg:gap-6 2xl:mb-[25px]">
        <UptimeCard overview={uptime.data?.data} window={uptimeWindow} onWindowChange={setUptimeWindow} retry={retryOf(uptime)} />
        <MonitorStatesCard overview={uptime.data?.data} retry={retryOf(uptime)} />
      </div>
      <div className="grid items-start gap-4 lg:gap-5 xl:grid-cols-[minmax(0,1fr)_var(--spacing-panel-compact)] 2xl:grid-cols-[minmax(0,1fr)_var(--spacing-panel)] 2xl:gap-[25px]">
        <div className="flex min-w-0 flex-col gap-4 2xl:gap-5">
          <ResponseTimeCard overview={response.data?.data} window={responseWindow} onWindowChange={setResponseWindow} retry={retryOf(response)} />
          <RecentIncidentsCard incidents={incidents.data?.data} now={now} retry={retryOf(incidents)} />
        </div>
        <MonitorPanel monitors={monitors.data?.data} retry={retryOf(monitors)} />
      </div>
    </div>
  );
}
