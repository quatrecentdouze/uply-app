import { Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { MonitorStatePill } from '@/features/monitors/components/MonitorStatePill';
import { displayState } from '@/features/monitors/model/dashboard';
import { intervalText } from '@/features/monitors/model/monitorForm';
import { pauseActionOf } from '@/features/monitors/model/monitorList';
import type { Monitor } from '@/shared/api/resources';
import { formatRelative } from '@/shared/time/format';
import { cellClass, DataTable, rowClass } from '@/shared/ui/data-table';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/shared/ui/dropdown-menu';
import { Ellipsis, Pause, Pencil, Play, Trash2 } from '@/shared/ui/icons';
import { uptimeSettings, uptimeTone } from '@/shared/uptime/uptime';

export interface MonitorActions {
  onEdit: (monitor: Monitor) => void;
  onTogglePause: (monitor: Monitor) => void;
  onDelete: (monitor: Monitor) => void;
}

interface MonitorTableProps {
  monitors: readonly Monitor[];
  now: Date;
  actions: MonitorActions | null;
}

const toneText = {
  positive: 'text-positive',
  warning: 'text-warning',
  alert: 'text-alert',
  neutral: 'text-muted-foreground',
} as const;

export function MonitorRowMenu({ monitor, actions }: { monitor: Monitor; actions: MonitorActions }) {
  const { t } = useTranslation(['monitors']);
  const pause = pauseActionOf(monitor);
  return (
    <DropdownMenu>
      <DropdownMenuTrigger aria-label={t('monitors:list.actions', { name: monitor.name })} className="grid size-9 place-items-center rounded-[10px] text-muted-foreground hover:bg-secondary hover:text-foreground">
        <Ellipsis className="size-5" aria-hidden="true" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem
          onSelect={() => {
            actions.onEdit(monitor);
          }}
        >
          <Pencil aria-hidden="true" />
          {t('monitors:list.edit')}
        </DropdownMenuItem>
        <DropdownMenuItem
          onSelect={() => {
            actions.onTogglePause(monitor);
          }}
        >
          {pause === 'pause' ? <Pause aria-hidden="true" /> : <Play aria-hidden="true" />}
          {pause === 'pause' ? t('monitors:list.pause') : t('monitors:list.resume')}
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          variant="destructive"
          onSelect={() => {
            actions.onDelete(monitor);
          }}
        >
          <Trash2 aria-hidden="true" />
          {t('monitors:list.delete')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

function MonitorRow({ monitor, now, actions }: { monitor: Monitor; now: Date; actions: MonitorActions | null }) {
  const { t, i18n } = useTranslation(['monitors']);
  const tone = uptimeTone(monitor.uptime_24h, uptimeSettings.thresholds);
  const interval = intervalText(monitor.interval_seconds);
  return (
    <tr className={rowClass}>
      <td className={`${cellClass} max-w-0 pl-[18px]`}>
        <Link to="/monitors/$monitorId" params={{ monitorId: monitor.id }} className="grid gap-0.5 hover:text-primary">
          <span className="truncate">{monitor.name}</span>
          <span className="truncate text-sm text-muted-foreground">{monitor.url}</span>
        </Link>
      </td>
      <td className={cellClass}>
        <MonitorStatePill state={displayState(monitor)} />
      </td>
      <td className={`${cellClass} whitespace-nowrap ${toneText[tone]}`}>
        {monitor.uptime_24h === null ? t('monitors:list.no_data') : t('monitors:uptime.percent', { value: monitor.uptime_24h })}
      </td>
      <td className={`${cellClass} whitespace-nowrap`}>
        {monitor.last_response_ms === null ? t('monitors:list.no_data') : t('monitors:response.average', { value: monitor.last_response_ms })}
      </td>
      <td className={`${cellClass} whitespace-nowrap text-muted-foreground`}>
        {monitor.last_checked_at === null ? t('monitors:list.never') : formatRelative(monitor.last_checked_at, now, { locale: i18n.language })}
      </td>
      <td className={`${cellClass} whitespace-nowrap text-muted-foreground`}>{t(interval.key, { count: interval.count })}</td>
      <td className={`${cellClass} pr-4 text-right`}>{actions === null ? null : <MonitorRowMenu monitor={monitor} actions={actions} />}</td>
    </tr>
  );
}

export function MonitorTable({ monitors, now, actions }: MonitorTableProps) {
  const { t } = useTranslation(['monitors']);
  return (
    <DataTable
      label={t('monitors:list.table')}
      className="min-w-[860px] table-fixed"
      head={[
        { label: t('monitors:list.monitor'), className: 'w-[32%]' },
        { label: t('monitors:list.state'), className: 'w-[12%]' },
        { label: t('monitors:list.uptime'), className: 'w-[12%]' },
        { label: t('monitors:list.response'), className: 'w-[11%]' },
        { label: t('monitors:list.checked'), className: 'w-[14%]' },
        { label: t('monitors:list.interval'), className: 'w-[13%]' },
        { label: t('monitors:list.actions_head'), className: 'w-[6%]', hidden: true },
      ]}
    >
      {monitors.map((monitor) => (
        <MonitorRow key={monitor.id} monitor={monitor} now={now} actions={actions} />
      ))}
    </DataTable>
  );
}
