import { Navigate, Outlet } from '@tanstack/react-router';
import { CurrentOrganizationProvider } from '@/features/organizations/components/CurrentOrganizationProvider';
import { useOrganizations } from '@/features/organizations/api/organizations';

export function AppLayout() {
  const organizations = useOrganizations().data ?? [];
  return (
    <CurrentOrganizationProvider organizations={organizations} fallback={<Navigate to="/organizations/new" />}>
      <main>
        <Outlet />
      </main>
    </CurrentOrganizationProvider>
  );
}
