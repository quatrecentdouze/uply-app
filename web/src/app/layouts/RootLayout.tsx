import { Outlet } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { DocumentTitle } from '@/app/shell/DocumentTitle';

export function RootLayout() {
  const { t } = useTranslation(['common']);
  return (
    <>
      <a
        href="#main"
        className="fixed top-3 left-4 z-[100] -translate-y-[200%] rounded-[9px] bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground focus:translate-y-0"
      >
        {t('common:skip_link')}
      </a>
      <DocumentTitle />
      <Outlet />
    </>
  );
}
