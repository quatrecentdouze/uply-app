import { Link, Outlet } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { Brand } from '@/shared/ui/brand';

export function AuthLayout() {
  const { t } = useTranslation(['common']);
  return (
    <div className="flex min-h-svh flex-col items-center bg-page px-4 py-10 sm:py-16">
      <Link to="/" aria-label={t('common:app.home')} className="mb-9 rounded-[14px]">
        <Brand name={t('common:app.name')} />
      </Link>
      <main id="main" tabIndex={-1} className="w-full max-w-[460px] outline-none">
        <Outlet />
      </main>
    </div>
  );
}
