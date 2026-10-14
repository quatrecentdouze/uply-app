import { useNavigate } from '@tanstack/react-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useWorkspace } from '@/app/hooks/useWorkspace';
import { MonitorEditor } from '@/app/monitors/MonitorEditor';
import { NotFoundPage } from '@/app/pages/NotFoundPage';
import { LinkedChannelsCard } from '@/features/alerting/components/LinkedChannelsCard';
import { MonitorIncidentsCard } from '@/features/incidents/components/MonitorIncidentsCard';
import { useDeleteMonitor, useMonitor, useMonitorStats, useUpdateMonitor } from '@/features/monitors/api/monitors';
import { MonitorHeaderCard, MonitorResponseCard, TimingBreakdownCard, UptimeStatCard } from '@/features/monitors/components/MonitorDetailCards';
import { RecentChecksCard } from '@/features/monitors/components/RecentChecksCard';
import { dashboardSettings } from '@/features/monitors/model/settings';
import { isFailure } from '@/shared/api/errors';
import { useMeta } from '@/shared/api/meta';
import type { Window } from '@/shared/api/queryKeys';
import type { Monitor } from '@/shared/api/resources';
import { useNow } from '@/shared/time/useNow';
import { ConfirmDialog } from '@/shared/ui/confirm-dialog';
import { Skeleton } from '@/shared/ui/skeleton';
import { useConfirmAction } from '@/shared/ui/use-confirm-action';
import { retryOf } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';

export function MonitorDetailPage({ monitorId }: { monitorId: string }) {
  const { t } = useTranslation(['monitors']);
  const navigate = useNavigate();
  const { organizationId, can } = useWorkspace();
  const monitoring = useMeta()?.monitoring ?? null;
  const monitor = useMonitor(organizationId, monitorId);
  const day = useMonitorStats(organizationId, monitorId, '24h');
  const week = useMonitorStats(organizationId, monitorId, '7d');
  const month = useMonitorStats(organizationId, monitorId, '30d');
  const [responseWindow, setResponseWindow] = useState<Window>(dashboardSettings.default_window);
  const response = { '24h': day, '7d': week, '30d': month }[responseWindow];
  const now = useNow(dashboardSettings.clock_tick_seconds);
  const update = useUpdateMonitor(organizationId);
  const remove = useDeleteMonitor(organizationId);
  const [editing, setEditing] = useState(false);
  const deletion = useConfirmAction(async (target: Monitor) => {
    await remove.mutateAsync(target.id);
    await navigate({ to: '/monitors' });
  });

  if (isFailure(monitor.error, 'not_found')) {
    return <NotFoundPage />;
  }
  const current = monitor.data?.data;
  if (current === undefined) {
    const retry = retryOf(monitor);
    return retry === null ? <Skeleton className="h-[140px] w-full" /> : <LoadError onRetry={retry} />;
  }
  const canManage = can('monitors.manage') && monitoring !== null;

  return (
    <div className="grid gap-4 2xl:gap-5">
      <MonitorHeaderCard
        monitor={current}
        now={now}
        actions={
          canManage
            ? {
                pausing: update.isPending,
                onTogglePause: (paused) => {
                  update.mutate({ monitorId: current.id, body: { is_paused: paused } });
                },
                onEdit: () => {
                  setEditing(true);
                },
                onDelete: () => {
                  deletion.ask(current);
                },
              }
            : null
        }
      />
      <div className="grid gap-4 sm:grid-cols-3 2xl:gap-5">
        <UptimeStatCard window="24h" stats={day.data} retry={retryOf(day)} />
        <UptimeStatCard window="7d" stats={week.data} retry={retryOf(week)} />
        <UptimeStatCard window="30d" stats={month.data} retry={retryOf(month)} />
      </div>
      <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_var(--spacing-panel-compact)] 2xl:grid-cols-[minmax(0,1fr)_var(--spacing-panel)] 2xl:gap-5">
        <div className="grid min-w-0 gap-4 2xl:gap-5">
          <MonitorResponseCard stats={response.data} window={responseWindow} onWindowChange={setResponseWindow} retry={retryOf(response)} />
          <RecentChecksCard organizationId={organizationId} monitorId={current.id} />
        </div>
        <div className="grid min-w-0 gap-4 2xl:gap-5">
          <TimingBreakdownCard stats={day.data} retry={retryOf(day)} />
          <MonitorIncidentsCard organizationId={organizationId} monitorId={current.id} now={now} size={dashboardSettings.detail_incidents} />
          <LinkedChannelsCard organizationId={organizationId} monitorId={current.id} />
        </div>
      </div>
      {monitoring === null ? null : <MonitorEditor open={editing} onOpenChange={setEditing} organizationId={organizationId} monitor={current} monitoring={monitoring} />}
      <ConfirmDialog
        {...deletion.dialog}
        texts={{
          title: t('monitors:delete.title', { name: current.name }),
          description: t('monitors:delete.description'),
          confirm: t('monitors:delete.confirm'),
          cancel: t('monitors:delete.cancel'),
        }}
      />
    </div>
  );
}
