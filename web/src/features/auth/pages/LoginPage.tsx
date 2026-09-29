import { zodResolver } from '@hookform/resolvers/zod';
import { Link, useNavigate, useRouter } from '@tanstack/react-router';
import { useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useLogin } from '@/features/auth/api/session';
import { SubmitButton } from '@/features/auth/components/SubmitButton';
import { type LoginValues, loginSchema } from '@/features/auth/model/forms';
import { failureOf } from '@/shared/api/errors';
import { reportFailure } from '@/shared/api/formFailure';
import { useValidationTexts } from '@/shared/i18n/useValidationTexts';
import { EntryCard } from '@/shared/ui/entry-card';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';

export type LoginNotice = 'password-reset';

interface LoginPageProps {
  redirectTo: string;
  notice: LoginNotice | null;
}

const fields = ['email', 'password'] as const;

export function LoginPage({ redirectTo, notice }: LoginPageProps) {
  const { t } = useTranslation(['auth', 'errors']);
  const validation = useValidationTexts();
  const router = useRouter();
  const navigate = useNavigate();
  const login = useLogin();
  const schema = useMemo(() => loginSchema(validation), [validation]);
  const form = useForm<LoginValues>({ resolver: zodResolver(schema), defaultValues: { email: '', password: '' } });
  const { errors, isSubmitting } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    try {
      const outcome = await login.mutateAsync(values);
      if (outcome.twoFactor) {
        await navigate({ to: '/two-factor-challenge', search: { redirect: redirectTo } });
        return;
      }
      router.history.push(redirectTo);
    } catch (error) {
      reportFailure(form.setError, failureOf(error), fields, (key) => t(key));
    }
  });

  return (
    <EntryCard
      title={t('auth:login.title')}
      description={t('auth:login.description')}
      footer={
        <>
          {t('auth:login.no_account')}{' '}
          <Link to="/register" search={{ redirect: redirectTo }} className="text-primary hover:underline">
            {t('auth:login.register_link')}
          </Link>
        </>
      }
    >
      <form noValidate onSubmit={(event) => void submit(event)} className="grid gap-5">
        {notice === 'password-reset' ? <FormAlert tone="positive">{t('auth:login.password_reset')}</FormAlert> : null}
        {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
        <FormField label={t('auth:login.email')} type="email" autoComplete="email" error={errors.email?.message} {...form.register('email')} />
        <FormField
          label={t('auth:login.password')}
          type="password"
          autoComplete="current-password"
          error={errors.password?.message}
          trailing={
            <Link to="/forgot-password" className="text-sm text-primary hover:underline">
              {t('auth:login.forgot')}
            </Link>
          }
          {...form.register('password')}
        />
        <SubmitButton pending={isSubmitting}>{t('auth:login.submit')}</SubmitButton>
      </form>
    </EntryCard>
  );
}
