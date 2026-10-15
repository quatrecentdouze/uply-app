import { Link, Outlet } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/shared/ui/brand';

export function PublicLayout() {
  const { t } = useTranslation(['common']);
  return (
    <div className="flex min-h-svh flex-col bg-page">
      <main id="main" tabIndex={-1} className="mx-auto outline-none w-[min(880px,calc(100%-32px))] flex-1 py-8 sm:py-12">
        <Outlet />
      </main>
      <footer className="border-t-2 border-divider py-6">
        <Link to="/" className="mx-auto flex w-fit items-center gap-2.5 rounded-[11px] px-2 py-1 text-sm text-quiet hover:text-foreground">
          <BrandMark className="size-8 rounded-[10px] [&>svg]:size-[22px]" />
          {t('common:public.powered_by')}
        </Link>
      </footer>
    </div>
  );
}
