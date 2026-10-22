import { useId } from 'react';
import { useFieldArray, useFormContext, useWatch } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { replacingHeaderRows } from '@/features/monitors/model/advancedForm';
import type { MonitorFormValues } from '@/features/monitors/model/monitorForm';
import { Button } from '@/shared/ui/button';
import { FormField } from '@/shared/ui/form-field';
import { Plus, Trash2 } from '@/shared/ui/icons';

interface RequestHeadersFieldProps {
  storedNames: readonly string[];
  maxHeaders: number;
}

function StoredHeaders({ names, onReplace }: { names: readonly string[]; onReplace: () => void }) {
  const { t } = useTranslation(['monitors']);
  return (
    <div className="grid gap-3 rounded-[13px] border-2 border-divider bg-surface-raised p-3.5">
      <ul aria-label={t('monitors:form.advanced.stored_headers')} className="flex flex-wrap gap-2">
        {names.map((name) => (
          <li key={name} className="rounded-[9px] bg-chip px-2.5 py-1 font-mono text-sm break-all">
            {name}
          </li>
        ))}
      </ul>
      <p className="text-sm text-muted-foreground">{t('monitors:form.advanced.stored_headers_hint')}</p>
      <Button type="button" variant="outline" className="h-10 justify-self-start rounded-[9px]" onClick={onReplace}>
        {t('monitors:form.advanced.replace_headers')}
      </Button>
    </div>
  );
}

export function RequestHeadersField({ storedNames, maxHeaders }: RequestHeadersFieldProps) {
  const { t } = useTranslation(['monitors']);
  const hintId = useId();
  const {
    control,
    register,
    setValue,
    formState: { errors },
  } = useFormContext<MonitorFormValues>();
  const { fields, append, remove, replace } = useFieldArray({ control, name: 'request_headers' });
  const replacing = useWatch({ control, name: 'replace_headers' });
  const listError = errors.request_headers?.root?.message ?? errors.request_headers?.message;
  const hint = storedNames.length > 0 ? t('monitors:form.advanced.replacing_headers_hint') : t('monitors:form.advanced.headers_hint', { max: maxHeaders });

  return (
    <fieldset className="grid min-w-0 gap-3" aria-describedby={replacing ? hintId : undefined}>
      <legend className="mb-2 text-sm text-muted-foreground">{t('monitors:form.advanced.headers')}</legend>
      {replacing ? (
        <>
          {fields.map((field, index) => {
            const rowErrors = errors.request_headers?.[index];
            return (
              <div
                key={field.id}
                role="group"
                aria-label={t('monitors:form.advanced.header_row', { index: index + 1 })}
                className="grid gap-3 rounded-[13px] border-2 border-divider p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto] sm:items-start sm:border-0 sm:p-0"
              >
                <FormField
                  label={t('monitors:form.advanced.header_name')}
                  autoComplete="off"
                  spellCheck={false}
                  error={rowErrors?.name?.message}
                  {...register(`request_headers.${index}.name`)}
                />
                <FormField
                  label={t('monitors:form.advanced.header_value')}
                  autoComplete="off"
                  spellCheck={false}
                  error={rowErrors?.value?.message}
                  {...register(`request_headers.${index}.value`)}
                />
                <Button
                  type="button"
                  variant="ghost"
                  aria-label={t('monitors:form.advanced.remove_header', { index: index + 1 })}
                  className="size-10 justify-self-end rounded-[9px] text-muted-foreground hover:text-alert sm:mt-7"
                  onClick={() => {
                    remove(index);
                  }}
                >
                  <Trash2 aria-hidden="true" />
                </Button>
              </div>
            );
          })}
          <Button
            type="button"
            variant="outline"
            disabled={fields.length >= maxHeaders}
            className="h-10 justify-self-start rounded-[9px]"
            onClick={() => {
              append({ name: '', value: '' });
            }}
          >
            <Plus aria-hidden="true" />
            {t('monitors:form.advanced.add_header')}
          </Button>
          <p id={hintId} className="text-sm text-faint">
            {hint}
          </p>
        </>
      ) : (
        <StoredHeaders
          names={storedNames}
          onReplace={() => {
            setValue('replace_headers', true);
            replace(replacingHeaderRows(storedNames));
          }}
        />
      )}
      {listError === undefined ? null : (
        <p role="alert" className="text-sm text-alert">
          {listError}
        </p>
      )}
    </fieldset>
  );
}
