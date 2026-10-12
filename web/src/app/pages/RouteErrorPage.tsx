import { type ErrorComponentProps, Link, useRouter } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { Button } from '@/shared/ui/button';
import { EntryCard } from '@/shared/ui/entry-card';
import { RotateCw } from '@/shared/ui/icons';

export function RouteErrorPage({ reset }: ErrorComponentProps) {
  const { t } = useTranslation(['common']);
  const router = useRouter();
  return (
    <div role="alert" className="mx-auto w-full max-w-[460px] px-4 py-10">
      <EntryCard
        title={t('common:route_error.title')}
        description={t('common:route_error.description')}
        footer={
          <Link to="/" className="text-primary hover:underline">
            {t('common:route_error.home')}
          </Link>
        }
      >
        <Button
          type="button"
          className="h-11 w-full rounded-[11px] text-base"
          onClick={() => {
            reset();
            void router.invalidate();
          }}
        >
          <RotateCw aria-hidden="true" />
          {t('common:route_error.retry')}
        </Button>
      </EntryCard>
    </div>
  );
}
