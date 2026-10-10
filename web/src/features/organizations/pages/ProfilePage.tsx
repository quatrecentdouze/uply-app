import { zodResolver } from '@hookform/resolvers/zod';
import { useMemo, useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useUpdatePassword, useUpdateProfile } from '@/features/organizations/api/account';
import { needsCurrentPassword, passwordSchema, type PasswordValues, profileBody, profileSchema, type ProfileValues } from '@/features/organizations/model/account';
import { failureOf } from '@/shared/api/errors';
import { reportFailure } from '@/shared/api/formFailure';
import type { AccountLengths } from '@/shared/api/meta';
import type { Me } from '@/shared/api/resources';
import { useValidationTexts } from '@/shared/i18n/useValidationTexts';
import { Button } from '@/shared/ui/button';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';
import { CardHeading, SurfaceCard } from '@/shared/ui/surface-card';

interface ProfilePageProps {
  me: Me;
  lengths: AccountLengths | null;
}

const profileFields = ['name', 'email', 'current_password'] as const;
const passwordFields = ['current_password', 'password', 'password_confirmation'] as const;

function ProfileForm({ me, lengths }: ProfilePageProps) {
  const { t } = useTranslation(['organizations', 'errors']);
  const validation = useValidationTexts();
  const update = useUpdateProfile();
  const schema = useMemo(() => profileSchema(validation, lengths, me), [validation, lengths, me]);
  const form = useForm<ProfileValues>({ resolver: zodResolver(schema), defaultValues: { name: me.name, email: me.email, current_password: '' } });
  const email = useWatch({ control: form.control, name: 'email' });
  const [notice, setNotice] = useState<'saved' | 'verify' | null>(null);
  const { errors, isSubmitting } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    setNotice(null);
    const body = profileBody(me, values);
    if (Object.keys(body).length === 0) {
      setNotice('saved');
      return;
    }
    try {
      const saved = await update.mutateAsync(body);
      form.reset({ name: saved.name, email: saved.email, current_password: '' });
      setNotice(body.email === undefined ? 'saved' : 'verify');
    } catch (error) {
      reportFailure(form.setError, failureOf(error), profileFields, (key) => t(key));
    }
  });

  return (
    <form noValidate onSubmit={(event) => void submit(event)} className="mt-4 grid max-w-xl gap-5">
      {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
      {notice === null ? null : <FormAlert tone="positive">{notice === 'saved' ? t('organizations:settings.profile.saved') : t('organizations:settings.profile.verify')}</FormAlert>}
      <FormField label={t('organizations:settings.profile.name')} autoComplete="name" error={errors.name?.message} {...form.register('name')} />
      <FormField label={t('organizations:settings.profile.email')} type="email" autoComplete="email" error={errors.email?.message} {...form.register('email')} />
      {needsCurrentPassword(me, email) ? (
        <FormField
          label={t('organizations:settings.profile.current_password')}
          type="password"
          autoComplete="current-password"
          hint={t('organizations:settings.profile.email_hint')}
          error={errors.current_password?.message}
          {...form.register('current_password')}
        />
      ) : null}
      <Button type="submit" disabled={isSubmitting} aria-busy={isSubmitting} className="h-10 w-fit rounded-[11px]">
        {t('organizations:settings.profile.save')}
      </Button>
    </form>
  );
}

function PasswordForm({ lengths }: { lengths: AccountLengths | null }) {
  const { t } = useTranslation(['organizations', 'errors']);
  const validation = useValidationTexts();
  const update = useUpdatePassword();
  const schema = useMemo(() => passwordSchema(validation, lengths), [validation, lengths]);
  const form = useForm<PasswordValues>({ resolver: zodResolver(schema), defaultValues: { current_password: '', password: '', password_confirmation: '' } });
  const [saved, setSaved] = useState(false);
  const { errors, isSubmitting } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    setSaved(false);
    try {
      await update.mutateAsync(values);
      form.reset();
      setSaved(true);
    } catch (error) {
      reportFailure(form.setError, failureOf(error), passwordFields, (key) => t(key));
    }
  });

  return (
    <form noValidate onSubmit={(event) => void submit(event)} className="mt-4 grid max-w-xl gap-5">
      {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
      {saved ? <FormAlert tone="positive">{t('organizations:settings.password_form.saved')}</FormAlert> : null}
      <FormField
        label={t('organizations:settings.profile.current_password')}
        type="password"
        autoComplete="current-password"
        error={errors.current_password?.message}
        {...form.register('current_password')}
      />
      <FormField label={t('organizations:settings.password_form.new')} type="password" autoComplete="new-password" error={errors.password?.message} {...form.register('password')} />
      <FormField
        label={t('organizations:settings.password_form.confirm')}
        type="password"
        autoComplete="new-password"
        error={errors.password_confirmation?.message}
        {...form.register('password_confirmation')}
      />
      <Button type="submit" disabled={isSubmitting} aria-busy={isSubmitting} className="h-10 w-fit rounded-[11px]">
        {t('organizations:settings.password_form.save')}
      </Button>
    </form>
  );
}

export function ProfilePage({ me, lengths }: ProfilePageProps) {
  const { t } = useTranslation(['organizations']);
  return (
    <div className="grid gap-4 2xl:gap-5">
      <SurfaceCard aria-labelledby="profile-title" className="p-4 sm:p-5">
        <CardHeading id="profile-title" title={t('organizations:settings.profile.title')} />
        <ProfileForm me={me} lengths={lengths} />
      </SurfaceCard>
      <SurfaceCard aria-labelledby="password-title" className="p-4 sm:p-5">
        <CardHeading id="password-title" title={t('organizations:settings.password_form.title')} />
        <PasswordForm lengths={lengths} />
      </SurfaceCard>
    </div>
  );
}
