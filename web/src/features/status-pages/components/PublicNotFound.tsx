import { Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { EntryCard } from '@/shared/ui/entry-card';

export function PublicNotFound() {
  const { t } = useTranslation(['status-pages']);
  return (
    <div className="mx-auto w-full max-w-[460px] py-6">
      <EntryCard
        title={t('status-pages:public.not_found.title')}
        description={t('status-pages:public.not_found.description')}
        footer={
          <Link to="/" className="text-primary hover:underline">
            {t('status-pages:public.not_found.home')}
          </Link>
        }
      />
    </div>
  );
}
