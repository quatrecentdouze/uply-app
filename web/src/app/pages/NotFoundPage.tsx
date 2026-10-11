import { Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { EntryCard } from '@/shared/ui/entry-card';

export function NotFoundPage() {
  const { t } = useTranslation(['common']);
  return (
    <div className="mx-auto w-full max-w-[460px] py-6">
      <EntryCard
        title={t('common:not_found.title')}
        description={t('common:not_found.description')}
        footer={
          <Link to="/dashboard" className="text-primary hover:underline">
            {t('common:not_found.home')}
          </Link>
        }
      />
    </div>
  );
}

export function StandaloneNotFoundPage() {
  return (
    <div className="flex min-h-svh items-center bg-page px-4">
      <NotFoundPage />
    </div>
  );
}
