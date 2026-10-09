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
import { ArrowLeft } from '@/shared/ui/icons';
import { Skeleton } from '@/shared/ui/skeleton';
import { useConfirmAction } from '@/shared/ui/use-confirm-action';

export function StatusPageEditorPage({ statusPageId }: { statusPageId: string }) {
  const { t } = useTranslation(['status-pages']);
  const navigate = useNavigate();
  const { organizationId, can } = useWorkspace();
  const meta = useMeta()?.status_pages ?? null;
  const page = useStatusPage(organizationId, statusPageId);
  const monitors = useMonitorList(organizationId, { perPage: dashboardSettings.list_size }).data?.data;
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
      <Link to="/status-pages" className="flex w-fit items-center gap-2 text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4" aria-hidden="true" />
        {t('status-pages:editor.back')}
      </Link>
      {current === undefined || meta === null || monitors === undefined ? (
        <Skeleton className="h-[320px] w-full" />
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
