import { getRouteApi } from '@tanstack/react-router';
import { PublicStatusPage } from '@/features/status-pages/pages/PublicStatusPage';

const publicStatusPageRoute = getRouteApi('/public/status/$slug');

export function PublicStatusPageRoute() {
  const { slug } = publicStatusPageRoute.useParams();
  return <PublicStatusPage key={slug} slug={slug} />;
}
