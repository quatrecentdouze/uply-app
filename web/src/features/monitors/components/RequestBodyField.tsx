import { useFormContext, useWatch } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import type { MonitorFormValues } from '@/features/monitors/model/monitorForm';
import { Button } from '@/shared/ui/button';
import { TextareaField } from '@/shared/ui/choice-fields';

export function RequestBodyField({ stored }: { stored: boolean }) {
  const { t } = useTranslation(['monitors']);
  const {
    control,
    register,
    setValue,
    formState: { errors },
  } = useFormContext<MonitorFormValues>();
  const replacing = useWatch({ control, name: 'replace_body' });

  if (!replacing) {
    return (
      <div className="grid gap-2">
        <p className="text-sm text-muted-foreground">{t('monitors:form.advanced.body')}</p>
        <div className="grid gap-3 rounded-[13px] border-2 border-divider bg-surface-raised p-3.5">
          <p className="text-base">{t('monitors:form.advanced.body_stored')}</p>
          <p className="text-sm text-muted-foreground">{t('monitors:form.advanced.body_stored_hint')}</p>
          <Button
            type="button"
            variant="outline"
            className="h-10 justify-self-start rounded-[9px]"
            onClick={() => {
              setValue('replace_body', true);
            }}
          >
            {t('monitors:form.advanced.replace_body')}
          </Button>
        </div>
      </div>
    );
  }

  return (
    <TextareaField
      label={t('monitors:form.advanced.body')}
      rows={5}
      autoComplete="off"
      spellCheck={false}
      hint={stored ? t('monitors:form.advanced.replacing_body_hint') : t('monitors:form.advanced.body_hint')}
      error={errors.request_body?.message}
      className="[&_textarea]:font-mono [&_textarea]:text-sm"
      {...register('request_body')}
    />
  );
}
