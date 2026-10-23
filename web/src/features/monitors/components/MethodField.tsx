import { useFormContext, useWatch } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { methodNotes, methodNoteTextKeys } from '@/features/monitors/model/advancedForm';
import { knownMethods, methodTextKeys, type MonitorFormValues, type MonitoringMeta } from '@/features/monitors/model/monitorForm';
import type { Monitor } from '@/shared/api/resources';
import { SelectField } from '@/shared/ui/choice-fields';

export function MethodField({ monitor, monitoring }: { monitor: Monitor | null; monitoring: MonitoringMeta }) {
  const { t } = useTranslation(['monitors']);
  const {
    control,
    register,
    formState: { errors },
  } = useFormContext<MonitorFormValues>();
  const [method, keyword, body] = useWatch({ control, name: ['method', 'keyword', 'request_body'] });
  const notes = methodNotes(method, { keyword, request_body: body }, monitor, monitoring);
  const options = knownMethods(monitoring.methods).map((candidate) => ({ value: candidate, label: t(methodTextKeys[candidate]) }));
  return (
    <SelectField
      label={t('monitors:form.method')}
      options={options}
      hint={notes.length === 0 ? undefined : <span className="text-warning">{notes.map((note) => t(methodNoteTextKeys[note])).join(' ')}</span>}
      error={errors.method?.message}
      {...register('method')}
    />
  );
}
