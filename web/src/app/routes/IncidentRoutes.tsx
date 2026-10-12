import { getRouteApi } from '@tanstack/react-router';
import { useWorkspace } from '@/app/hooks/useWorkspace';
import { NotFoundPage } from '@/app/pages/NotFoundPage';
import { IncidentDetailPage } from '@/features/incidents/pages/IncidentDetailPage';
import { IncidentsPage } from '@/features/incidents/pages/IncidentsPage';
import { dashboardSettings } from '@/features/monitors/model/settings';
import { useNow } from '@/shared/time/useNow';

const incidentRoute = getRouteApi('/app/pages/incidents/$incidentId');

export function IncidentsRoute() {
  const { organizationId } = useWorkspace();
  const now = useNow(dashboardSettings.clock_tick_seconds);
  return <IncidentsPage organizationId={organizationId} now={now} />;
}

export function IncidentRoute() {
  const { organizationId } = useWorkspace();
  const now = useNow(dashboardSettings.clock_tick_seconds);
  return <IncidentDetailPage organizationId={organizationId} incidentId={incidentRoute.useParams().incidentId} now={now} notFound={<NotFoundPage />} />;
}
