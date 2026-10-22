import { useFormContext } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { keywordMatchTextKeys, knownKeywordMatches } from '@/features/monitors/model/advancedForm';
import type { MonitorFormValues, MonitoringMeta } from '@/features/monitors/model/monitorForm';
import { SelectField } from '@/shared/ui/choice-fields';
import { FormField } from '@/shared/ui/form-field';

export function KeywordFields({ matches }: { matches: MonitoringMeta['keyword_matches'] }) {
  const { t } = useTranslation(['monitors']);
  const {
    register,
    formState: { errors },
  } = useFormContext<MonitorFormValues>();
  const options = knownKeywordMatches(matches).map((match) => ({ value: match, label: t(keywordMatchTextKeys[match]) }));
  return (
    <div className="grid gap-5 sm:grid-cols-[minmax(0,1fr)_190px]">
      <FormField
        label={t('monitors:form.advanced.keyword')}
        autoComplete="off"
        spellCheck={false}
        hint={t('monitors:form.advanced.keyword_hint')}
        error={errors.keyword?.message}
        {...register('keyword')}
      />
      <SelectField label={t('monitors:form.advanced.keyword_match')} options={options} error={errors.keyword_match?.message} {...register('keyword_match')} />
    </div>
  );
}
