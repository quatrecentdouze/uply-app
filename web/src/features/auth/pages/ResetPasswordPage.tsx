import { zodResolver } from '@hookform/resolvers/zod';
import { Link, useNavigate } from '@tanstack/react-router';
import { useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useResetPassword } from '@/features/auth/api/session';
import { SubmitButton } from '@/features/auth/components/SubmitButton';
import { type ResetLink, type ResetPasswordValues, resetPasswordSchema } from '@/features/auth/model/forms';
import { failureOf } from '@/shared/api/errors';
import { reportFailure } from '@/shared/api/formFailure';
import { useMeta } from '@/shared/api/meta';
import { useValidationTexts } from '@/shared/i18n/useValidationTexts';
import { EntryCard } from '@/shared/ui/entry-card';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';

const fields = ['email', 'password', 'password_confirmation'] as const;

function BackToSignIn() {
  const { t } = useTranslation(['auth']);
  return (
    <Link to="/login" className="text-primary hover:underline">
      {t('auth:forgot.back')}
    </Link>
  );
}

function ResetForm({ link }: { link: ResetLink }) {
  const { t } = useTranslation(['auth', 'errors']);
  const validation = useValidationTexts();
  const navigate = useNavigate();
  const reset = useResetPassword();
  const lengths = useMeta()?.organizations.lengths ?? null;
  const schema = useMemo(() => resetPasswordSchema(validation, lengths), [validation, lengths]);
  const form = useForm<ResetPasswordValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: link.email, password: '', password_confirmation: '' },
  });
  const { errors, isSubmitting } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    try {
      await reset.mutateAsync({ token: link.token, ...values });
      await navigate({ to: '/login', search: { notice: 'password-reset' } });
    } catch (error) {
      reportFailure(form.setError, failureOf(error), fields, (key) => t(key));
    }
  });

  return (
    <form noValidate onSubmit={(event) => void submit(event)} className="grid gap-5">
      {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
      <FormField label={t('auth:reset.email')} type="email" autoComplete="email" error={errors.email?.message} {...form.register('email')} />
      <FormField
        label={t('auth:reset.password')}
        type="password"
        autoComplete="new-password"
        error={errors.password?.message}
        hint={lengths === null ? undefined : t('auth:register.password_hint', { ...lengths.password })}
        {...form.register('password')}
      />
      <FormField
        label={t('auth:reset.password_confirmation')}
        type="password"
        autoComplete="new-password"
        error={errors.password_confirmation?.message}
        {...form.register('password_confirmation')}
      />
      <SubmitButton pending={isSubmitting}>{t('auth:reset.submit')}</SubmitButton>
    </form>
  );
}

export function ResetPasswordPage({ link }: { link: ResetLink | null }) {
  const { t } = useTranslation(['auth']);
  return (
    <EntryCard title={t('auth:reset.title')} description={t('auth:reset.description')} footer={<BackToSignIn />}>
      {link === null ? (
        <div className="grid gap-5">
          <FormAlert tone="alert">{t('auth:reset.invalid_link')}</FormAlert>
          <Link to="/forgot-password" className="text-primary hover:underline">
            {t('auth:reset.request_new')}
          </Link>
        </div>
      ) : (
        <ResetForm link={link} />
      )}
    </EntryCard>
  );
}
