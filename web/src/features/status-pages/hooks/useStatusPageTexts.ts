import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { StatusPageFormTexts } from '@/features/status-pages/model/statusPageForm';
import { useValidationTexts } from '@/shared/i18n/useValidationTexts';

export function useStatusPageTexts(): StatusPageFormTexts {
  const { t } = useTranslation(['status-pages']);
  const base = useValidationTexts();
  return useMemo(
    () => ({
      ...base,
      maxLength: (max) => t('status-pages:form.errors.max_length', { max }),
      slug: (bounds) => t('status-pages:form.errors.slug', { ...bounds }),
      tooManyMonitors: (max) => t('status-pages:form.errors.too_many_monitors', { max }),
    }),
    [base, t],
  );
}
