import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { MonitorFormTexts } from '@/features/monitors/model/monitorForm';
import { useValidationTexts } from '@/shared/i18n/useValidationTexts';

export function useMonitorFormTexts(): MonitorFormTexts {
  const { t } = useTranslation(['monitors']);
  const base = useValidationTexts();
  return useMemo(
    () => ({
      ...base,
      url: t('monitors:form.errors.url'),
      maxLength: (max) => t('monitors:form.errors.max_length', { max }),
      statusRanges: t('monitors:form.errors.status_ranges'),
      tooManyRanges: (max) => t('monitors:form.errors.too_many_ranges', { max }),
      interval: t('monitors:form.errors.interval'),
      timeout: (bounds) => t('monitors:form.errors.timeout', { ...bounds }),
      headerName: t('monitors:form.errors.header_name'),
      headerRefused: t('monitors:form.errors.header_refused'),
      headerDuplicate: t('monitors:form.errors.header_duplicate'),
      headerValue: t('monitors:form.errors.header_value'),
      tooManyHeaders: (max) => t('monitors:form.errors.too_many_headers', { max }),
      threshold: (bounds) => t('monitors:form.errors.threshold', { ...bounds }),
    }),
    [base, t],
  );
}
