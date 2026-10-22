import { useFormContext } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import type { MonitorFormValues, MonitoringMeta } from '@/features/monitors/model/monitorForm';
import { FormField } from '@/shared/ui/form-field';

export function ThresholdFields({ thresholds }: { thresholds: MonitoringMeta['thresholds'] }) {
  const { t } = useTranslation(['monitors']);
  const {
    register,
    formState: { errors },
  } = useFormContext<MonitorFormValues>();
  return (
    <div className="grid gap-5 sm:grid-cols-2">
      <FormField
        label={t('monitors:form.advanced.failure_threshold')}
        type="number"
        inputMode="numeric"
        min={thresholds.min}
        max={thresholds.max}
        hint={t('monitors:form.advanced.in_a_row')}
        error={errors.failure_threshold?.message}
        {...register('failure_threshold')}
      />
      <FormField
        label={t('monitors:form.advanced.recovery_threshold')}
        type="number"
        inputMode="numeric"
        min={thresholds.min}
        max={thresholds.max}
        hint={t('monitors:form.advanced.in_a_row')}
        error={errors.recovery_threshold?.message}
        {...register('recovery_threshold')}
      />
    </div>
  );
}
