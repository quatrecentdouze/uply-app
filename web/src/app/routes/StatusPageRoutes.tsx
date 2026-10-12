import { getRouteApi } from '@tanstack/react-router';
import { useWorkspace } from '@/app/hooks/useWorkspace';
import { StatusPageEditorPage } from '@/app/pages/StatusPageEditorPage';
import { StatusPagesPage } from '@/features/status-pages/pages/StatusPagesPage';
import { useMeta } from '@/shared/api/meta';

const statusPageRoute = getRouteApi('/app/pages/status-pages/$statusPageId');

export function StatusPagesRoute() {
  const { organizationId, can } = useWorkspace();
  const meta = useMeta()?.status_pages ?? null;
  return <StatusPagesPage organizationId={organizationId} meta={meta} canManage={can('status-pages.manage')} />;
}

export function StatusPageRoute() {
  return <StatusPageEditorPage statusPageId={statusPageRoute.useParams().statusPageId} />;
}
