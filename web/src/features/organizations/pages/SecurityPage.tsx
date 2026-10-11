import { zodResolver } from '@hookform/resolvers/zod';
import { useMemo, useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { type TwoFactorSetup, useDeleteAccount, usePasswordConfirmation, useTwoFactor } from '@/features/organizations/api/account';
import { PasswordConfirmDialog } from '@/features/organizations/components/PasswordConfirmDialog';
import { type ConfirmationCodeValues, confirmationCodeSchema, qrImageSource, restingStep, type TwoFactorStep } from '@/features/organizations/model/account';
import { errorTextKey, failureOf, fieldErrors } from '@/shared/api/errors';
import type { Me } from '@/shared/api/resources';
import { Button } from '@/shared/ui/button';
import { ConfirmDialog } from '@/shared/ui/confirm-dialog';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';
import { KeyRound, ShieldCheck, Trash2 } from '@/shared/ui/icons';
import { CardHeading, Pill, SurfaceCard } from '@/shared/ui/surface-card';

interface SecurityPageProps {
  me: Me;
  onAccountDeleted: () => Promise<void>;
}

type Action = () => Promise<void>;

function useFailureText() {
  const { t } = useTranslation(['errors']);
  return (error: unknown) => {
    const failure = failureOf(error);
    return fieldErrors(failure)[0]?.[1] ?? t(errorTextKey(failure));
  };
}

function PasswordStep({ onConfirmed, onCancel }: { onConfirmed: (password: string) => Promise<void>; onCancel: () => void }) {
  const { t } = useTranslation(['organizations']);
  const failureText = useFailureText();
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  return (
    <form
      noValidate
      className="mt-4 grid max-w-md gap-4"
      onSubmit={(event) => {
        event.preventDefault();
        setBusy(true);
        setError(null);
        onConfirmed(password).catch((failure: unknown) => {
          setBusy(false);
          setError(failureText(failure));
        });
      }}
    >
      <p className="text-muted-foreground">{t('organizations:settings.security.password_first')}</p>
      {error === null ? null : <FormAlert tone="alert">{error}</FormAlert>}
      <FormField
        label={t('organizations:settings.password')}
        type="password"
        autoComplete="current-password"
        value={password}
        onChange={(event) => {
          setPassword(event.target.value);
        }}
      />
      <div className="flex gap-2">
        <Button type="button" variant="outline" className="h-10 rounded-[11px]" onClick={onCancel}>
          {t('organizations:settings.cancel')}
        </Button>
        <Button type="submit" disabled={busy || password === ''} aria-busy={busy} className="h-10 rounded-[11px]">
          {t('organizations:settings.security.confirm_password')}
        </Button>
      </div>
    </form>
  );
}

function ScanStep({ setup, onSecretKey, onConfirm, onCancel }: { setup: TwoFactorSetup; onSecretKey: () => Promise<string>; onConfirm: (code: string) => Promise<void>; onCancel: () => void }) {
  const { t } = useTranslation(['organizations', 'errors']);
  const failureText = useFailureText();
  const schema = useMemo(() => confirmationCodeSchema({ code: t('organizations:settings.security.code_error') }), [t]);
  const form = useForm<ConfirmationCodeValues>({ resolver: zodResolver(schema), defaultValues: { code: '' } });
  const [secret, setSecret] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const { errors, isSubmitting } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    setError(null);
    try {
      await onConfirm(values.code.trim());
    } catch (failure) {
      setError(failureText(failure));
    }
  });

  return (
    <div className="mt-4 grid gap-5 md:grid-cols-[auto_minmax(0,1fr)] md:items-start">
      <div className="grid justify-items-start gap-3">
        <img src={qrImageSource(setup.svg)} alt={t('organizations:settings.security.qr_alt')} className="size-[200px] rounded-[13px] bg-bright p-3" />
        {secret === null ? (
          <Button
            type="button"
            variant="ghost"
            className="h-8 px-2 text-sm text-muted-foreground"
            onClick={() => {
              onSecretKey().then(setSecret, (failure: unknown) => {
                setError(failureText(failure));
              });
            }}
          >
            <KeyRound aria-hidden="true" />
            {t('organizations:settings.security.show_key')}
          </Button>
        ) : (
          <p className="max-w-[220px] text-sm break-all text-muted-foreground">
            {t('organizations:settings.security.key')} <code className="font-mono text-foreground">{secret}</code>
          </p>
        )}
      </div>
      <form noValidate onSubmit={(event) => void submit(event)} className="grid max-w-md gap-4">
        <p className="text-muted-foreground">{t('organizations:settings.security.scan')}</p>
        {error === null ? null : <FormAlert tone="alert">{error}</FormAlert>}
        <FormField
          label={t('organizations:settings.security.code')}
          inputMode="numeric"
          autoComplete="one-time-code"
          error={errors.code?.message}
          {...form.register('code')}
        />
        <div className="flex gap-2">
          <Button type="button" variant="outline" className="h-10 rounded-[11px]" onClick={onCancel}>
            {t('organizations:settings.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting} aria-busy={isSubmitting} className="h-10 rounded-[11px]">
            {t('organizations:settings.security.activate')}
          </Button>
        </div>
      </form>
    </div>
  );
}

function CodesStep({ codes, onDone }: { codes: readonly string[]; onDone: () => void }) {
  const { t } = useTranslation(['organizations']);
  return (
    <div className="mt-4 grid max-w-xl gap-4">
      <FormAlert tone="positive">{t('organizations:settings.security.codes_intro')}</FormAlert>
      <ul aria-label={t('organizations:settings.security.codes')} className="grid grid-cols-2 gap-2 rounded-[13px] bg-chip p-4 font-mono text-base">
        {codes.map((code) => (
          <li key={code}>{code}</li>
        ))}
      </ul>
      <Button type="button" className="h-10 w-fit rounded-[11px]" onClick={onDone}>
        {t('organizations:settings.security.codes_done')}
      </Button>
    </div>
  );
}

function TwoFactorCard({ me }: { me: Me }) {
  const { t } = useTranslation(['organizations']);
  const failureText = useFailureText();
  const passwords = usePasswordConfirmation();
  const twoFactor = useTwoFactor();
  const [step, setStep] = useState<TwoFactorStep>(restingStep(me));
  const [setup, setSetup] = useState<TwoFactorSetup | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [turningOff, setTurningOff] = useState(false);
  const [busy, setBusy] = useState(false);
  const waiting = useRef<Action | null>(null);

  const rest = () => {
    waiting.current = null;
    setSetup(null);
    setStep(restingStep(me));
  };

  const run = async (action: Action) => {
    try {
      await action();
    } catch (failure) {
      if (failureOf(failure).kind === 'password_confirmation') {
        waiting.current = action;
        setStep({ kind: 'password' });
        return;
      }
      throw failure;
    }
  };

  const withPassword = (action: Action) => {
    setError(null);
    setBusy(true);
    passwords
      .status()
      .then(async (confirmed) => {
        if (confirmed) {
          await run(action);
        } else {
          waiting.current = action;
          setStep({ kind: 'password' });
        }
      })
      .catch((failure: unknown) => {
        setError(failureText(failure));
      })
      .finally(() => {
        setBusy(false);
      });
  };

  const enable: Action = async () => {
    await twoFactor.enable();
    setSetup(await twoFactor.setup());
    setStep({ kind: 'scan' });
  };

  const regenerate: Action = async () => {
    await twoFactor.regenerate();
    setStep({ kind: 'codes', codes: await twoFactor.recoveryCodes() });
  };

  const disable: Action = async () => {
    await twoFactor.disable();
    setStep({ kind: 'off' });
  };

  return (
    <SurfaceCard aria-labelledby="two-factor-title" className="p-4 sm:p-5">
      <CardHeading
        id="two-factor-title"
        title={t('organizations:settings.security.two_factor')}
        action={me.two_factor_enabled ? <Pill tone="positive">{t('organizations:settings.security.on')}</Pill> : <Pill tone="neutral">{t('organizations:settings.security.off')}</Pill>}
      />
      <p className="mt-2 max-w-2xl text-muted-foreground">{t('organizations:settings.security.two_factor_description')}</p>
      {error === null ? null : (
        <div className="mt-4 max-w-xl">
          <FormAlert tone="alert">{error}</FormAlert>
        </div>
      )}
      {step.kind === 'off' ? (
        <Button
          type="button"
          className="mt-4 h-10 rounded-[11px]"
          disabled={busy}
          aria-busy={busy}
          onClick={() => {
            withPassword(enable);
          }}
        >
          <ShieldCheck aria-hidden="true" />
          {t('organizations:settings.security.enable')}
        </Button>
      ) : null}
      {step.kind === 'password' ? (
        <PasswordStep
          onCancel={rest}
          onConfirmed={async (password) => {
            await passwords.confirm(password);
            const action = waiting.current;
            waiting.current = null;
            if (action !== null) {
              await run(action);
            }
          }}
        />
      ) : null}
      {step.kind === 'scan' && setup !== null ? (
        <ScanStep
          setup={setup}
          onSecretKey={twoFactor.secretKey}
          onCancel={rest}
          onConfirm={async (code) => {
            await twoFactor.confirm(code);
            setSetup(null);
            setStep({ kind: 'codes', codes: await twoFactor.recoveryCodes() });
          }}
        />
      ) : null}
      {step.kind === 'codes' ? (
        <CodesStep
          codes={step.codes}
          onDone={() => {
            setStep({ kind: 'on' });
          }}
        />
      ) : null}
      {step.kind === 'on' ? (
        <div className="mt-4 flex flex-wrap gap-2">
          <Button
            type="button"
            variant="outline"
            className="h-10 rounded-[11px]"
            disabled={busy}
            onClick={() => {
              withPassword(regenerate);
            }}
          >
            <KeyRound aria-hidden="true" />
            {t('organizations:settings.security.regenerate')}
          </Button>
          <Button
            type="button"
            variant="destructive"
            className="h-10 rounded-[11px]"
            disabled={busy}
            onClick={() => {
              setTurningOff(true);
            }}
          >
            {t('organizations:settings.security.disable')}
          </Button>
        </div>
      ) : null}
      <ConfirmDialog
        open={turningOff}
        onOpenChange={setTurningOff}
        busy={false}
        error={null}
        texts={{
          title: t('organizations:settings.security.disable_title'),
          description: t('organizations:settings.security.disable_description'),
          confirm: t('organizations:settings.security.disable'),
          cancel: t('organizations:settings.cancel'),
        }}
        onConfirm={() => {
          setTurningOff(false);
          withPassword(disable);
        }}
      />
    </SurfaceCard>
  );
}

export function SecurityPage({ me, onAccountDeleted }: SecurityPageProps) {
  const { t } = useTranslation(['organizations']);
  const remove = useDeleteAccount();
  const [deleting, setDeleting] = useState(false);
  return (
    <div className="grid gap-4 2xl:gap-5">
      <TwoFactorCard me={me} />
      <SurfaceCard aria-labelledby="account-delete-title" className="border-alert/30 p-4 sm:p-5">
        <CardHeading id="account-delete-title" title={t('organizations:settings.security.delete_title')} />
        <p className="mt-2 max-w-2xl text-muted-foreground">{t('organizations:settings.security.delete_description')}</p>
        <Button
          type="button"
          variant="destructive"
          className="mt-4 h-10 rounded-[11px]"
          onClick={() => {
            setDeleting(true);
          }}
        >
          <Trash2 aria-hidden="true" />
          {t('organizations:settings.security.delete')}
        </Button>
      </SurfaceCard>
      <PasswordConfirmDialog
        open={deleting}
        onOpenChange={setDeleting}
        texts={{
          title: t('organizations:settings.security.delete_confirm_title'),
          description: t('organizations:settings.security.delete_confirm_description'),
          confirm: t('organizations:settings.security.delete'),
          cancel: t('organizations:settings.cancel'),
        }}
        onConfirm={async (password) => {
          await remove.mutateAsync(password);
          setDeleting(false);
          await onAccountDeleted();
        }}
      />
    </div>
  );
}
