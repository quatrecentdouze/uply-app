import { zodResolver } from '@hookform/resolvers/zod';
import { Link } from '@tanstack/react-router';
import { useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useForgotPassword } from '@/features/auth/api/session';
import { SubmitButton } from '@/features/auth/components/SubmitButton';
import { type ForgotPasswordValues, forgotPasswordSchema } from '@/features/auth/model/forms';
import { failureOf } from '@/shared/api/errors';
import { reportFailure } from '@/shared/api/formFailure';
import { useValidationTexts } from '@/shared/i18n/useValidationTexts';
import { EntryCard } from '@/shared/ui/entry-card';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';

const fields = ['email'] as const;

export function ForgotPasswordPage() {
  const { t } = useTranslation(['auth', 'errors']);
  const validation = useValidationTexts();
  const forgot = useForgotPassword();
  const schema = useMemo(() => forgotPasswordSchema(validation), [validation]);
  const form = useForm<ForgotPasswordValues>({ resolver: zodResolver(schema), defaultValues: { email: '' } });
  const { errors, isSubmitting, isSubmitSuccessful } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    try {
      await forgot.mutateAsync(values.email);
    } catch (error) {
      reportFailure(form.setError, failureOf(error), fields, (key) => t(key));
    }
  });

  return (
    <EntryCard
      title={t('auth:forgot.title')}
      description={t('auth:forgot.description')}
      footer={
        <Link to="/login" className="text-primary hover:underline">
          {t('auth:forgot.back')}
        </Link>
      }
    >
      <form noValidate onSubmit={(event) => void submit(event)} className="grid gap-5">
        {isSubmitSuccessful && forgot.isSuccess ? <FormAlert tone="positive">{t('auth:forgot.sent')}</FormAlert> : null}
        {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
        <FormField label={t('auth:forgot.email')} type="email" autoComplete="email" error={errors.email?.message} {...form.register('email')} />
        <SubmitButton pending={isSubmitting}>{t('auth:forgot.submit')}</SubmitButton>
      </form>
    </EntryCard>
  );
}
