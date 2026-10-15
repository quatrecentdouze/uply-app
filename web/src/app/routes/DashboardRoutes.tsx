import { getRouteApi, useNavigate } from '@tanstack/react-router';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DashboardPage } from '@/app/pages/DashboardPage';
import { CircleCheck, X } from '@/shared/ui/icons';

const dashboardRoute = getRouteApi('/app/pages/dashboard');

function VerifiedNotice({ onDismiss }: { onDismiss: () => void }) {
  const { t } = useTranslation(['auth']);
  return (
    <div role="status" className="mb-4 flex items-center gap-3 rounded-[13px] border border-positive/30 bg-positive-soft py-1.5 pr-1.5 pl-4 text-positive sm:mb-5">
      <CircleCheck className="size-5 shrink-0" aria-hidden="true" />
      <p className="flex-1 text-sm">{t('auth:verify.verified')}</p>
      <button type="button" onClick={onDismiss} aria-label={t('auth:verify.dismiss')} className="grid size-10 place-items-center rounded-[10px] hover:bg-positive/10">
        <X className="size-4" aria-hidden="true" />
      </button>
    </div>
  );
}

export function DashboardRoute() {
  const { verified } = dashboardRoute.useSearch();
  const navigate = useNavigate();
  const [notice, setNotice] = useState(verified === true);

  useEffect(() => {
    if (verified === true) {
      void navigate({ to: '/dashboard', search: {}, replace: true });
    }
  }, [verified, navigate]);

  return (
    <>
      {notice ? (
        <VerifiedNotice
          onDismiss={() => {
            setNotice(false);
          }}
        />
      ) : null}
      <DashboardPage />
    </>
  );
}
