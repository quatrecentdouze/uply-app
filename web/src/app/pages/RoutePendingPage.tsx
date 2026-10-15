import { useTranslation } from 'react-i18next';

export function RoutePendingPage() {
  const { t } = useTranslation(['common']);
  return (
    <div role="status" aria-label={t('common:loading')} className="grid min-h-[50svh] place-items-center">
      <span aria-hidden="true" className="size-8 animate-spin rounded-full border-2 border-line-strong border-t-primary" />
    </div>
  );
}
