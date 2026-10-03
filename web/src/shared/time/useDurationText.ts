import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { durationParts } from '@/shared/time/format';

export function useDurationText(): (seconds: number) => string {
  const { t } = useTranslation(['common']);
  return useCallback(
    (seconds: number) => {
      const parts = durationParts(seconds);
      switch (parts.kind) {
        case 'seconds':
          return t('common:duration.seconds', { seconds: parts.seconds });
        case 'minutes':
          return t('common:duration.minutes', { minutes: parts.minutes, seconds: parts.seconds });
        case 'hours':
          return t('common:duration.hours', { hours: parts.hours, minutes: parts.minutes });
        case 'days':
          return t('common:duration.days', { days: parts.days, hours: parts.hours });
      }
    },
    [t],
  );
}
