import { Link, useNavigate } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { useWorkspace } from '@/app/hooks/useWorkspace';
import { NotFoundPage } from '@/app/pages/NotFoundPage';
import { useMonitorList } from '@/features/monitors/api/monitors';
import { dashboardSettings } from '@/features/monitors/model/settings';
import { useDeleteStatusPage, useStatusPage } from '@/features/status-pages/api/statusPages';
import { StatusPageEditor } from '@/features/status-pages/components/StatusPageEditor';
import { isFailure } from '@/shared/api/errors';
import { useMeta } from '@/shared/api/meta';
import type { StatusPage } from '@/shared/api/resources';
import { ConfirmDialog } from '@/shared/ui/confirm-dialog';
import { ArrowLeft, ExternalLink } from '@/shared/ui/icons';
import { Skeleton } from '@/shared/ui/skeleton';
import { useConfirmAction } from '@/shared/ui/use-confirm-action';
import { retryOf } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';

export function StatusPageEditorPage({ statusPageId }: { statusPageId: string }) {
  const { t } = useTranslation(['status-pages']);
  const navigate = useNavigate();
  const { organizationId, can } = useWorkspace();
  const meta = useMeta()?.status_pages ?? null;
  const page = useStatusPage(organizationId, statusPageId);
  const monitorList = useMonitorList(organizationId, { perPage: dashboardSettings.list_size });
  const monitors = monitorList.data?.data;
  const retry = retryOf(page) ?? retryOf(monitorList);
  const remove = useDeleteStatusPage(organizationId);
  const deletion = useConfirmAction(async (target: StatusPage) => {
    await remove.mutateAsync(target.id);
    await navigate({ to: '/status-pages' });
  });

  if (isFailure(page.error, 'not_found')) {
    return <NotFoundPage />;
  }
  const current = page.data;

  return (
    <div className="grid gap-4 2xl:gap-5">
      <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <Link to="/status-pages" className="flex min-h-10 w-fit items-center gap-2 text-muted-foreground hover:text-foreground">
          <ArrowLeft className="size-4" aria-hidden="true" />
          {t('status-pages:editor.back')}
        </Link>
        {current?.is_published === true ? (
          <Link
            to="/status/$slug"
            params={{ slug: current.slug }}
            target="_blank"
            rel="noopener noreferrer"
            className="flex min-h-10 items-center gap-2 rounded-[11px] border-2 border-line-strong bg-surface-raised px-3.5 text-sm hover:bg-secondary"
          >
            {t('status-pages:editor.open_public')}{' '}
            <span className="sr-only">{t('status-pages:editor.new_tab')}</span>
            <ExternalLink className="size-4" aria-hidden="true" />
          </Link>
        ) : null}
      </div>
      {current === undefined || meta === null || monitors === undefined ? (
        retry === null ? (
          <Skeleton className="h-[320px] w-full" />
        ) : (
          <LoadError onRetry={retry} />
        )
      ) : (
        <StatusPageEditor
          organizationId={organizationId}
          page={current}
          meta={meta}
          monitors={monitors.map((monitor) => ({ id: monitor.id, name: monitor.name }))}
          canManage={can('status-pages.manage')}
          onDelete={() => {
            deletion.ask(current);
          }}
        />
      )}
      <ConfirmDialog
        {...deletion.dialog}
        texts={{
          title: t('status-pages:delete.title', { name: current?.title ?? '' }),
          description: t('status-pages:delete.description'),
          confirm: t('status-pages:delete.confirm'),
          cancel: t('status-pages:delete.cancel'),
        }}
      />
    </div>
  );
}
