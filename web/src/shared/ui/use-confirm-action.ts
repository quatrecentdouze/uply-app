import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type ApiFailure, errorTextKey, failureOf, fieldErrors } from '@/shared/api/errors';

export interface ConfirmAction<Target> {
  target: Target | null;
  ask: (target: Target) => void;
  dialog: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    busy: boolean;
    error: string | null;
    onConfirm: () => void;
  };
}

function firstFieldError(failure: ApiFailure): string | null {
  return fieldErrors(failure)[0]?.[1] ?? null;
}

export function useConfirmAction<Target>(run: (target: Target) => Promise<void>): ConfirmAction<Target> {
  const { t } = useTranslation(['errors']);
  const [target, setTarget] = useState<Target | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const ask = useCallback((next: Target) => {
    setError(null);
    setTarget(next);
  }, []);

  const onOpenChange = useCallback(
    (open: boolean) => {
      if (!open && !busy) {
        setTarget(null);
        setError(null);
      }
    },
    [busy],
  );

  const onConfirm = useCallback(() => {
    if (target === null) {
      return;
    }
    setBusy(true);
    setError(null);
    run(target).then(
      () => {
        setBusy(false);
        setTarget(null);
      },
      (failure: unknown) => {
        const reason = failureOf(failure);
        setBusy(false);
        setError(firstFieldError(reason) ?? t(errorTextKey(reason)));
      },
    );
  }, [run, t, target]);

  return { target, ask, dialog: { open: target !== null, onOpenChange, busy, error, onConfirm } };
}
