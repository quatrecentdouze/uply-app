import { zodResolver } from '@hookform/resolvers/zod';
import { type ReactNode, useMemo, useState } from 'react';
import { Controller, FormProvider, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useSaveMonitor } from '@/features/monitors/api/monitors';
import { MonitorAdvancedSection } from '@/features/monitors/components/MonitorAdvancedSection';
import { useMonitorFormTexts } from '@/features/monitors/hooks/useMonitorFormTexts';
import { advancedFields, advancedOpenAtStart, headerRowFields, isHeaderRowField, touchesAdvanced } from '@/features/monitors/model/advancedForm';
import {
  createMonitorBody,
  hasChanges,
  intervalText,
  knownMethods,
  methodTextKeys,
  type MonitorFormValues,
  type MonitoringMeta,
  monitorFormSchema,
  monitorFormValues,
  updateMonitorBody,
} from '@/features/monitors/model/monitorForm';
import { failureOf } from '@/shared/api/errors';
import { reportFailure, withFieldRoots } from '@/shared/api/formFailure';
import type { Monitor } from '@/shared/api/resources';
import { Button } from '@/shared/ui/button';
import { SelectField, SwitchField } from '@/shared/ui/choice-fields';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';

export interface MonitorFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  organizationId: string;
  monitor: Monitor | null;
  monitoring: MonitoringMeta;
  extra?: ReactNode;
  onSaved: (monitor: Monitor) => Promise<void>;
}

const fields = ['name', 'url', 'method', 'interval_seconds', 'timeout_seconds', 'accepted_status_codes', 'follow_redirects', ...advancedFields] as const;

function MonitorForm({ organizationId, monitor, monitoring, extra, onSaved, onOpenChange }: Omit<MonitorFormDialogProps, 'open'>) {
  const { t } = useTranslation(['monitors', 'errors']);
  const texts = useMonitorFormTexts();
  const save = useSaveMonitor(organizationId);
  const schema = useMemo(() => monitorFormSchema(texts, monitoring), [texts, monitoring]);
  const form = useForm<MonitorFormValues>({ resolver: zodResolver(schema), defaultValues: monitorFormValues(monitor, monitoring) });
  const [advancedOpen, setAdvancedOpen] = useState(() => advancedOpenAtStart(monitor, monitoring));
  const { errors, isSubmitting } = form.formState;

  const submit = form.handleSubmit(
    async (values) => {
      try {
        if (monitor === null) {
          await onSaved(await save.create(createMonitorBody(values, monitoring)));
        } else {
          const body = updateMonitorBody(monitor, values, monitoring);
          await onSaved(hasChanges(body) ? await save.update(monitor.id, body) : monitor);
        }
        onOpenChange(false);
      } catch (error) {
        const failure = withFieldRoots(failureOf(error), isHeaderRowField);
        if (failure.kind === 'validation' && touchesAdvanced(Object.keys(failure.fields))) {
          setAdvancedOpen(true);
        }
        reportFailure(form.setError, failure, [...fields, ...headerRowFields(values.request_headers.length)], (key) => t(key));
      }
    },
    (invalid) => {
      if (touchesAdvanced(Object.keys(invalid))) {
        setAdvancedOpen(true);
      }
    },
  );

  const methodOptions = knownMethods(monitoring.methods).map((method) => ({ value: method, label: t(methodTextKeys[method]) }));
  const intervalOptions = monitoring.intervals.allowed_seconds.map((seconds) => {
    const text = intervalText(seconds);
    return { value: String(seconds), label: t(text.key, { count: text.count }) };
  });

  return (
    <FormProvider {...form}>
      <form noValidate onSubmit={(event) => void submit(event)} className="grid gap-5">
        {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
        <FormField label={t('monitors:form.name')} autoComplete="off" error={errors.name?.message} {...form.register('name')} />
        <FormField label={t('monitors:form.url')} type="url" inputMode="url" autoComplete="off" placeholder={t('monitors:form.url_placeholder')} error={errors.url?.message} {...form.register('url')} />
        <div className="grid gap-5 sm:grid-cols-2">
          <SelectField label={t('monitors:form.method')} options={methodOptions} error={errors.method?.message} {...form.register('method')} />
          <SelectField label={t('monitors:form.interval')} options={intervalOptions} error={errors.interval_seconds?.message} {...form.register('interval_seconds')} />
        </div>
        <div className="grid gap-5 sm:grid-cols-2">
          <FormField
            label={t('monitors:form.timeout')}
            type="number"
            inputMode="numeric"
            min={monitoring.timeouts.min_seconds}
            max={monitoring.timeouts.max_seconds}
            error={errors.timeout_seconds?.message}
            {...form.register('timeout_seconds')}
          />
          <FormField
            label={t('monitors:form.accepted_codes')}
            autoComplete="off"
            hint={t('monitors:form.accepted_codes_hint')}
            error={errors.accepted_status_codes?.message}
            {...form.register('accepted_status_codes')}
          />
        </div>
        <Controller
          control={form.control}
          name="follow_redirects"
          render={({ field }) => (
            <SwitchField label={t('monitors:form.follow_redirects')} description={t('monitors:form.follow_redirects_hint')} checked={field.value} onCheckedChange={field.onChange} />
          )}
        />
        {extra}
        <MonitorAdvancedSection open={advancedOpen} onOpenChange={setAdvancedOpen} monitoring={monitoring} />
        <DialogFooter className="gap-2">
          <Button
            type="button"
            variant="outline"
            className="h-10 rounded-[9px]"
            onClick={() => {
              onOpenChange(false);
            }}
          >
            {t('monitors:form.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting} aria-busy={isSubmitting} className="h-10 rounded-[9px]">
            {monitor === null ? t('monitors:form.create') : t('monitors:form.save')}
          </Button>
        </DialogFooter>
      </form>
    </FormProvider>
  );
}

export function MonitorFormDialog({ open, ...props }: MonitorFormDialogProps) {
  const { t } = useTranslation(['monitors']);
  return (
    <Dialog open={open} onOpenChange={props.onOpenChange}>
      <DialogContent className="max-h-[92svh] overflow-y-auto rounded-[20px] border-2 border-line-strong bg-panel p-[25px] ring-0 sm:max-w-[600px]">
        <DialogHeader>
          <DialogTitle className="text-[23px] font-medium">
            {props.monitor === null ? t('monitors:form.title_create') : t('monitors:form.title_edit', { name: props.monitor.name })}
          </DialogTitle>
          <DialogDescription>{t('monitors:form.description')}</DialogDescription>
        </DialogHeader>
        {open ? <MonitorForm key={props.monitor?.id ?? 'new'} {...props} /> : null}
      </DialogContent>
    </Dialog>
  );
}
