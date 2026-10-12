import { useWorkspace } from '@/app/hooks/useWorkspace';
import { AlertChannelsPage } from '@/features/alerting/pages/AlertChannelsPage';
import { useMeta } from '@/shared/api/meta';

export function AlertChannelsRoute() {
  const { organizationId, can } = useWorkspace();
  const alerting = useMeta()?.alerting ?? null;
  return <AlertChannelsPage organizationId={organizationId} alerting={alerting} canManage={can('alert-channels.manage')} />;
}
