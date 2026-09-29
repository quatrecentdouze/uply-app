import { zodResolver } from '@hookform/resolvers/zod';
import { Link, useRouter } from '@tanstack/react-router';
import { useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useTwoFactorChallenge } from '@/features/auth/api/session';
import { SubmitButton } from '@/features/auth/components/SubmitButton';
import { twoFactorBody, type TwoFactorMode, type TwoFactorValues, twoFactorSchema } from '@/features/auth/model/forms';
import { failureOf } from '@/shared/api/errors';
import { reportFailure } from '@/shared/api/formFailure';
import { useValidationTexts } from '@/shared/i18n/useValidationTexts';
import { EntryCard } from '@/shared/ui/entry-card';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';

const serverFields = { code: 'code', recovery: 'recovery_code' } as const;

function ChallengeForm({ mode, redirectTo, onSwitch }: { mode: TwoFactorMode; redirectTo: string; onSwitch: () => void }) {
  const { t } = useTranslation(['auth', 'errors']);
  const validation = useValidationTexts();
  const router = useRouter();
  const challenge = useTwoFactorChallenge();
  const schema = useMemo(() => twoFactorSchema({ required: validation.required, code: t('auth:two_factor.invalid_code') }, mode), [validation, mode, t]);
  const form = useForm<TwoFactorValues>({ resolver: zodResolver(schema), defaultValues: { answer: '' } });
  const { errors, isSubmitting } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    try {
      await challenge.mutateAsync(twoFactorBody(mode, values.answer));
      router.history.push(redirectTo);
    } catch (error) {
      const failure = failureOf(error);
      if (failure.kind === 'validation') {
        const message = failure.fields[serverFields[mode]]?.[0];
        if (message !== undefined) {
          form.setError('answer', { type: 'server', message });
          return;
        }
      }
      reportFailure(form.setError, failure, [], (key) => t(key));
    }
  });

  return (
    <form noValidate onSubmit={(event) => void submit(event)} className="grid gap-5">
      {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
      <FormField
        label={mode === 'code' ? t('auth:two_factor.code') : t('auth:two_factor.recovery_code')}
        autoComplete="one-time-code"
        inputMode={mode === 'code' ? 'numeric' : 'text'}
        error={errors.answer?.message}
        {...form.register('answer')}
      />
      <SubmitButton pending={isSubmitting}>{t('auth:two_factor.submit')}</SubmitButton>
      <button type="button" onClick={onSwitch} className="justify-self-start text-sm text-primary hover:underline">
        {mode === 'code' ? t('auth:two_factor.use_recovery') : t('auth:two_factor.use_code')}
      </button>
    </form>
  );
}

export function TwoFactorChallengePage({ redirectTo }: { redirectTo: string }) {
  const { t } = useTranslation(['auth']);
  const [mode, setMode] = useState<TwoFactorMode>('code');
  return (
    <EntryCard
      title={t('auth:two_factor.title')}
      description={mode === 'code' ? t('auth:two_factor.code_description') : t('auth:two_factor.recovery_description')}
      footer={
        <Link to="/login" className="text-primary hover:underline">
          {t('auth:two_factor.back')}
        </Link>
      }
    >
      <ChallengeForm
        key={mode}
        mode={mode}
        redirectTo={redirectTo}
        onSwitch={() => {
          setMode(mode === 'code' ? 'recovery' : 'code');
        }}
      />
    </EntryCard>
  );
}
