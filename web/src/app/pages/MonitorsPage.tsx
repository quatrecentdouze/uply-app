import { useNavigate } from '@tanstack/react-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useWorkspace } from '@/app/hooks/useWorkspace';
import { MonitorEditor } from '@/app/monitors/MonitorEditor';
import { useDeleteMonitor, useMonitorList, useUpdateMonitor } from '@/features/monitors/api/monitors';
import { type MonitorActions, MonitorTable } from '@/features/monitors/components/MonitorTable';
import { emptinessOf, listFilters, type StateFilter, stateFilters, stateFilterTextKeys } from '@/features/monitors/model/monitorList';
import { dashboardSettings } from '@/features/monitors/model/settings';
import { useMeta } from '@/shared/api/meta';
import type { Monitor } from '@/shared/api/resources';
import { useDebounced } from '@/shared/time/useDebounced';
import { useNow } from '@/shared/time/useNow';
import { Button } from '@/shared/ui/button';
import { ConfirmDialog } from '@/shared/ui/confirm-dialog';
import { Plus, Search } from '@/shared/ui/icons';
import { Input } from '@/shared/ui/input';
import { Skeleton } from '@/shared/ui/skeleton';
import { SurfaceCard } from '@/shared/ui/surface-card';
import { useConfirmAction } from '@/shared/ui/use-confirm-action';
import { retryOf } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';

interface EditorState {
  open: boolean;
  monitor: Monitor | null;
}

export function MonitorsPage() {
  const { t } = useTranslation(['monitors']);
  const navigate = useNavigate();
  const { organizationId, can } = useWorkspace();
  const monitoring = useMeta()?.monitoring ?? null;
  const [state, setState] = useState<StateFilter>('all');
  const [search, setSearch] = useState('');
  const filters = listFilters(state, useDebounced(search, dashboardSettings.search_delay_ms), dashboardSettings.list_size);
  const monitors = useMonitorList(organizationId, filters);
  const now = useNow(dashboardSettings.clock_tick_seconds);
  const update = useUpdateMonitor(organizationId);
  const remove = useDeleteMonitor(organizationId);
  const [editor, setEditor] = useState<EditorState>({ open: false, monitor: null });
  const deletion = useConfirmAction(async (monitor: Monitor) => {
    await remove.mutateAsync(monitor.id);
  });
  const canManage = can('monitors.manage') && monitoring !== null;

  const actions: MonitorActions | null = canManage
    ? {
        onEdit: (monitor) => {
          setEditor({ open: true, monitor });
        },
        onTogglePause: (monitor) => {
          update.mutate({ monitorId: monitor.id, body: { is_paused: !monitor.is_paused } });
        },
        onDelete: deletion.ask,
      }
    : null;

  const list = monitors.data?.data;
  const emptiness = list === undefined ? 'none' : emptinessOf(list, filters);
  const retry = retryOf(monitors);

  return (
    <SurfaceCard aria-labelledby="monitors-title" className="px-2.5 pt-4 pb-3 sm:px-5">
      <h2 id="monitors-title" className="sr-only">
        {t('monitors:list.title')}
      </h2>
      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div role="group" aria-label={t('monitors:list.filters.label')} className="flex flex-wrap items-center gap-[5px]">
          {stateFilters.map((candidate) => (
            <button
              key={candidate}
              type="button"
              aria-pressed={candidate === state}
              onClick={() => {
                setState(candidate);
              }}
              className="h-10 rounded-[22px] md:h-[31px] px-[17px] text-base text-muted-foreground aria-pressed:bg-bright aria-pressed:text-ink"
            >
              {t(stateFilterTextKeys[candidate])}
            </button>
          ))}
        </div>
        <label className="relative ml-auto min-w-56 flex-1 sm:max-w-80">
          <span className="sr-only">{t('monitors:list.search')}</span>
          <Search className="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-quiet" aria-hidden="true" />
          <Input
            type="search"
            value={search}
            placeholder={t('monitors:list.search')}
            onChange={(event) => {
              setSearch(event.target.value);
            }}
            className="h-10 rounded-[13px] border-2 border-field-line bg-field pl-10 text-base"
          />
        </label>
        {canManage ? (
          <Button
            type="button"
            className="h-10 rounded-[11px] px-4"
            onClick={() => {
              setEditor({ open: true, monitor: null });
            }}
          >
            <Plus aria-hidden="true" />
            {t('monitors:list.add')}
          </Button>
        ) : null}
      </div>
      {list === undefined ? (
        retry === null ? (
          <Skeleton className="h-[220px] w-full" />
        ) : (
          <LoadError onRetry={retry} />
        )
      ) : emptiness === 'none' ? (
        <MonitorTable monitors={list} now={now} actions={actions} />
      ) : (
        <p className="py-10 text-center text-muted-foreground">{emptiness === 'first' ? t('monitors:list.empty') : t('monitors:list.no_match')}</p>
      )}
      {monitoring === null ? null : (
        <MonitorEditor
          open={editor.open}
          onOpenChange={(open) => {
            setEditor((current) => ({ ...current, open }));
          }}
          organizationId={organizationId}
          monitor={editor.monitor}
          monitoring={monitoring}
          onCreated={(monitor) => {
            void navigate({ to: '/monitors/$monitorId', params: { monitorId: monitor.id } });
          }}
        />
      )}
      <ConfirmDialog
        {...deletion.dialog}
        texts={{
          title: t('monitors:delete.title', { name: deletion.target?.name ?? '' }),
          description: t('monitors:delete.description'),
          confirm: t('monitors:delete.confirm'),
          cancel: t('monitors:delete.cancel'),
        }}
      />
    </SurfaceCard>
  );
}
