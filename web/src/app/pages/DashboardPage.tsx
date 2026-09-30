import { useTranslation } from 'react-i18next';
import { useCurrentOrganization } from '@/features/organizations/hooks/currentOrganization';

export function DashboardPage() {
  const { t } = useTranslation(['common']);
  const { organization } = useCurrentOrganization();
  return (
    <h1 className="p-6 text-2xl">
      {t('common:pages.dashboard')} {organization.name}
    </h1>
  );
}
