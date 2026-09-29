import { useNavigate } from '@tanstack/react-router';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useLogout, useMe, useResendVerification } from '@/features/auth/api/session';
import { errorTextKey, failureOf } from '@/shared/api/errors';
import { Button } from '@/shared/ui/button';
import { EntryCard } from '@/shared/ui/entry-card';
import { FormAlert } from '@/shared/ui/form-alert';

export function VerifyEmailPage() {
  const { t } = useTranslation(['auth', 'errors']);
  const navigate = useNavigate();
  const me = useMe().data ?? null;
  const resend = useResendVerification();
  const logout = useLogout();
  const verified = me?.email_verified_at !== null && me !== null;

  useEffect(() => {
    if (verified) {
      void navigate({ to: '/' });
    }
  }, [verified, navigate]);

  const switchAccount = async () => {
    await logout.mutateAsync().catch(() => undefined);
    await navigate({ to: '/login' });
  };

  return (
    <EntryCard
      title={t('auth:verify.title')}
      description={t('auth:verify.description', { email: me?.email ?? '' })}
      footer={
        <button type="button" onClick={() => void switchAccount()} className="text-primary hover:underline">
          {t('auth:verify.switch_account')}
        </button>
      }
    >
      <div className="grid gap-5">
        {resend.isSuccess ? <FormAlert tone="positive">{t('auth:verify.sent')}</FormAlert> : null}
        {resend.isError ? <FormAlert tone="alert">{t(errorTextKey(failureOf(resend.error)))}</FormAlert> : null}
        <Button
          type="button"
          disabled={resend.isPending}
          aria-busy={resend.isPending}
          onClick={() => {
            resend.mutate();
          }}
          className="h-11 w-full rounded-[9px] text-sm font-medium"
        >
          {t('auth:verify.resend')}
        </Button>
      </div>
    </EntryCard>
  );
}
