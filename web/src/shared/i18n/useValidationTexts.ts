import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

export interface BaseValidationTexts {
  required: string;
  email: string;
  length: (bounds: { min: number; max: number }) => string;
  mismatch: string;
}

export function useValidationTexts(): BaseValidationTexts {
  const { t } = useTranslation(['common']);
  return useMemo(
    () => ({
      required: t('common:validation.required'),
      email: t('common:validation.email'),
      length: (bounds) => t('common:validation.length', bounds),
      mismatch: t('common:validation.mismatch'),
    }),
    [t],
  );
}
