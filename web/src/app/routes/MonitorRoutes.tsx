import { getRouteApi } from '@tanstack/react-router';
import { MonitorDetailPage } from '@/app/pages/MonitorDetailPage';

const monitorRoute = getRouteApi('/app/pages/monitors/$monitorId');

export function MonitorRoute() {
  return <MonitorDetailPage monitorId={monitorRoute.useParams().monitorId} />;
}
