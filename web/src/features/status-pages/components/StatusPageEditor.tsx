import { zodResolver } from '@hookform/resolvers/zod';
import { useMemo, useState } from 'react';
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useUpdateStatusPage } from '@/features/status-pages/api/statusPages';
import { useStatusPageTexts } from '@/features/status-pages/hooks/useStatusPageTexts';
import {
  availableMonitors,
  type MonitorOption,
  monitorName,
  type StatusPageFormValues,
  type StatusPagesMeta,
  statusPageFormValues,
  statusPageSchema,
  updateStatusPageBody,
} from '@/features/status-pages/model/statusPageForm';
import { failureOf } from '@/shared/api/errors';
import { reportFailure, withFieldRoots } from '@/shared/api/formFailure';
import type { StatusPage } from '@/shared/api/resources';
import { Button } from '@/shared/ui/button';
import { SelectField, SwitchField, TextareaField } from '@/shared/ui/choice-fields';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';
import { ArrowDown, ArrowUp, Plus, Trash2, X } from '@/shared/ui/icons';
import { Input } from '@/shared/ui/input';
import { CardHeading, SurfaceCard } from '@/shared/ui/surface-card';

interface StatusPageEditorProps {
  organizationId: string;
  page: StatusPage;
  meta: StatusPagesMeta;
  monitors: readonly MonitorOption[];
  canManage: boolean;
  onDelete: () => void;
}

const fields = ['title', 'slug', 'description', 'is_published', 'monitors'] as const;

export function StatusPageEditor({ organizationId, page, meta, monitors, canManage, onDelete }: StatusPageEditorProps) {
  const { t } = useTranslation(['status-pages', 'errors']);
  const texts = useStatusPageTexts();
  const update = useUpdateStatusPage(organizationId);
  const schema = useMemo(() => statusPageSchema(texts, meta), [texts, meta]);
  const form = useForm<StatusPageFormValues>({ resolver: zodResolver(schema), values: statusPageFormValues(page), resetOptions: { keepDirtyValues: false } });
  const list = useFieldArray({ control: form.control, name: 'monitors', keyName: 'key' });
  const slug = useWatch({ control: form.control, name: 'slug' });
  const [adding, setAdding] = useState('');
  const [saved, setSaved] = useState(false);
  const { errors, isSubmitting, isDirty } = form.formState;
  const choices = availableMonitors(monitors, list.fields);
  const full = list.fields.length >= meta.limits.max_monitors_per_page;
  const readOnly = !canManage;

  const submit = form.handleSubmit(async (values) => {
    setSaved(false);
    const body = updateStatusPageBody(page, values);
    if (Object.keys(body).length === 0) {
      return;
    }
    try {
      await update.mutateAsync({ statusPageId: page.id, body });
      setSaved(true);
    } catch (error) {
      reportFailure(form.setError, withFieldRoots(failureOf(error)), fields, (key) => t(key));
    }
  });

  return (
    <form noValidate onSubmit={(event) => void submit(event)} className="grid gap-4 2xl:gap-5">
      <SurfaceCard aria-labelledby="page-settings-title" className="grid gap-5 p-4 sm:p-5">
        <CardHeading id="page-settings-title" title={t('status-pages:editor.settings')} />
        {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
        {saved && !isDirty ? <FormAlert tone="positive">{t('status-pages:editor.saved')}</FormAlert> : null}
        <div className="grid gap-5 md:grid-cols-2">
          <FormField label={t('status-pages:form.title')} autoComplete="off" disabled={readOnly} error={errors.title?.message} {...form.register('title')} />
          <FormField
            label={t('status-pages:form.slug')}
            autoComplete="off"
            spellCheck={false}
            disabled={readOnly}
            hint={t('status-pages:editor.address', { slug })}
            error={errors.slug?.message}
            {...form.register('slug')}
          />
        </div>
        <TextareaField
          label={t('status-pages:editor.description')}
          disabled={readOnly}
          hint={t('status-pages:editor.description_hint', { max: meta.lengths.description.max })}
          error={errors.description?.message}
          {...form.register('description')}
        />
        <Controller
          control={form.control}
          name="is_published"
          render={({ field }) => (
            <SwitchField label={t('status-pages:editor.published')} description={t('status-pages:editor.published_hint')} checked={field.value} disabled={readOnly} onCheckedChange={field.onChange} />
          )}
        />
      </SurfaceCard>
      <SurfaceCard aria-labelledby="page-monitors-title" className="grid gap-4 p-4 sm:p-5">
        <CardHeading id="page-monitors-title" title={t('status-pages:editor.monitors')} info={t('status-pages:editor.monitors_info')} />
        {errors.monitors?.message === undefined ? null : <FormAlert tone="alert">{errors.monitors.message}</FormAlert>}
        {list.fields.length === 0 ? (
          <p className="text-muted-foreground">{t('status-pages:editor.no_monitors')}</p>
        ) : (
          <ol className="grid gap-2.5">
            {list.fields.map((item, index) => {
              const name = monitorName(monitors, item.monitor_id) ?? t('status-pages:editor.unknown_monitor');
              const error = errors.monitors?.[index]?.display_name?.message;
              return (
                <li key={item.key} className="grid gap-1.5 rounded-[13px] border-2 border-divider bg-surface-raised px-3.5 py-3">
                  <div className="flex flex-wrap items-center gap-3">
                    <span className="w-6 text-sm text-faint">{index + 1}</span>
                    <span className="min-w-32 flex-1 truncate">{name}</span>
                    <label className="flex min-w-56 flex-[2] items-center gap-2">
                      <span className="sr-only">{t('status-pages:editor.display_name', { name })}</span>
                      <Input
                        placeholder={t('status-pages:editor.display_name_placeholder', { name })}
                        disabled={readOnly}
                        aria-invalid={error === undefined ? undefined : true}
                        className="h-10 rounded-[11px] border-field-line bg-field px-3 text-base"
                        {...form.register(`monitors.${index}.display_name`)}
                      />
                    </label>
                    {readOnly ? null : (
                      <div className="flex gap-1">
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          aria-label={t('status-pages:editor.move_up', { name })}
                          disabled={index === 0}
                          onClick={() => {
                            list.move(index, index - 1);
                          }}
                        >
                          <ArrowUp aria-hidden="true" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          aria-label={t('status-pages:editor.move_down', { name })}
                          disabled={index === list.fields.length - 1}
                          onClick={() => {
                            list.move(index, index + 1);
                          }}
                        >
                          <ArrowDown aria-hidden="true" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          aria-label={t('status-pages:editor.remove', { name })}
                          onClick={() => {
                            list.remove(index);
                          }}
                        >
                          <X aria-hidden="true" />
                        </Button>
                      </div>
                    )}
                  </div>
                  {error === undefined ? null : (
                    <p role="alert" className="text-sm text-alert">
                      {error}
                    </p>
                  )}
                </li>
              );
            })}
          </ol>
        )}
        {readOnly || choices.length === 0 ? null : (
          <div className="flex flex-wrap items-end gap-3">
            <SelectField
              className="min-w-64 flex-1"
              label={t('status-pages:editor.add_label')}
              value={adding}
              disabled={full}
              options={[{ value: '', label: t('status-pages:editor.choose') }, ...choices.map((choice) => ({ value: choice.id, label: choice.name }))]}
              onChange={(event) => {
                setAdding(event.target.value);
              }}
            />
            <Button
              type="button"
              variant="outline"
              className="h-11 rounded-[11px]"
              disabled={adding === '' || full}
              onClick={() => {
                list.append({ monitor_id: adding, display_name: '' });
                setAdding('');
              }}
            >
              <Plus aria-hidden="true" />
              {t('status-pages:editor.add')}
            </Button>
          </div>
        )}
      </SurfaceCard>
      {readOnly ? null : (
        <div className="flex flex-wrap items-center gap-3">
          <Button type="button" variant="destructive" className="h-10 rounded-[11px]" onClick={onDelete}>
            <Trash2 aria-hidden="true" />
            {t('status-pages:editor.delete')}
          </Button>
          <Button
            type="button"
            variant="outline"
            className="ml-auto h-10 rounded-[11px]"
            disabled={!isDirty || isSubmitting}
            onClick={() => {
              setSaved(false);
              form.reset(statusPageFormValues(page));
            }}
          >
            {t('status-pages:editor.discard')}
          </Button>
          <Button type="submit" className="h-10 rounded-[11px]" disabled={!isDirty || isSubmitting} aria-busy={isSubmitting}>
            {t('status-pages:editor.save')}
          </Button>
        </div>
      )}
    </form>
  );
}
