import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { errorTextKey, failureOf, fieldErrors } from '@/shared/api/errors';
import { ConfirmDialog, type ConfirmTexts } from '@/shared/ui/confirm-dialog';
import { FormField } from '@/shared/ui/form-field';

interface PasswordConfirmDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  texts: ConfirmTexts;
  onConfirm: (password: string) => Promise<void>;
}

export function PasswordConfirmDialog({ open, onOpenChange, texts, onConfirm }: PasswordConfirmDialogProps) {
  const { t } = useTranslation(['organizations', 'errors']);
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const change = (next: boolean) => {
    if (busy) {
      return;
    }
    if (!next) {
      setPassword('');
      setError(null);
    }
    onOpenChange(next);
  };

  const confirm = () => {
    setBusy(true);
    setError(null);
    onConfirm(password).then(
      () => {
        setBusy(false);
        setPassword('');
      },
      (failure: unknown) => {
        const reason = failureOf(failure);
        setBusy(false);
        setError(fieldErrors(reason)[0]?.[1] ?? t(errorTextKey(reason)));
      },
    );
  };

  return (
    <ConfirmDialog open={open} onOpenChange={change} texts={texts} busy={busy} error={error} onConfirm={confirm} canConfirm={password !== ''}>
      <FormField
        label={t('organizations:settings.password')}
        type="password"
        autoComplete="current-password"
        value={password}
        onChange={(event) => {
          setPassword(event.target.value);
        }}
      />
    </ConfirmDialog>
  );
}
