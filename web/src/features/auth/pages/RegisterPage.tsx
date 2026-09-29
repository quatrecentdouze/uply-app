import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
import { Link, useRouter } from '@tanstack/react-router';
import { useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useRegister } from '@/features/auth/api/session';
import { SubmitButton } from '@/features/auth/components/SubmitButton';
import { type RegisterValues, registerSchema } from '@/features/auth/model/forms';
import { useApiClient } from '@/shared/api/apiClientContext';
import { failureOf } from '@/shared/api/errors';
import { reportFailure } from '@/shared/api/formFailure';
import { metaQuery } from '@/shared/api/meta';
import { useValidationTexts } from '@/shared/i18n/useValidationTexts';
import { EntryCard } from '@/shared/ui/entry-card';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';

const fields = ['name', 'email', 'password', 'password_confirmation'] as const;

export function RegisterPage({ redirectTo }: { redirectTo: string }) {
  const { t } = useTranslation(['auth', 'errors']);
  const validation = useValidationTexts();
  const router = useRouter();
  const register = useRegister();
  const lengths = useQuery(metaQuery(useApiClient())).data?.lengths ?? null;
  const schema = useMemo(() => registerSchema(validation, lengths), [validation, lengths]);
  const form = useForm<RegisterValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: '', email: '', password: '', password_confirmation: '' },
  });
  const { errors, isSubmitting } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    try {
      await register.mutateAsync(values);
      router.history.push(redirectTo);
    } catch (error) {
      reportFailure(form.setError, failureOf(error), fields, (key) => t(key));
    }
  });

  return (
    <EntryCard
      title={t('auth:register.title')}
      description={t('auth:register.description')}
      footer={
        <>
          {t('auth:register.have_account')}{' '}
          <Link to="/login" search={{ redirect: redirectTo }} className="text-primary hover:underline">
            {t('auth:register.login_link')}
          </Link>
        </>
      }
    >
      <form noValidate onSubmit={(event) => void submit(event)} className="grid gap-5">
        {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
        <FormField label={t('auth:register.name')} autoComplete="name" error={errors.name?.message} {...form.register('name')} />
        <FormField label={t('auth:register.email')} type="email" autoComplete="email" error={errors.email?.message} {...form.register('email')} />
        <FormField
          label={t('auth:register.password')}
          type="password"
          autoComplete="new-password"
          error={errors.password?.message}
          hint={lengths === null ? undefined : t('auth:register.password_hint', { ...lengths.password })}
          {...form.register('password')}
        />
        <FormField
          label={t('auth:register.password_confirmation')}
          type="password"
          autoComplete="new-password"
          error={errors.password_confirmation?.message}
          {...form.register('password_confirmation')}
        />
        <SubmitButton pending={isSubmitting}>{t('auth:register.submit')}</SubmitButton>
      </form>
    </EntryCard>
  );
}
