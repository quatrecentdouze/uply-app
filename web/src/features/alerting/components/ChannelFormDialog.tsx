import { zodResolver } from '@hookform/resolvers/zod';
import { useMemo } from 'react';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useCreateChannel, useUpdateChannel } from '@/features/alerting/api/alertChannels';
import { type AlertingMeta, type ChannelFormValues, channelFormSchema, channelFormValues, createChannelBody, updateChannelBody } from '@/features/alerting/model/channelForm';
import { channelTypes, channelTypeTextKeys } from '@/features/alerting/model/channels';
import { failureOf } from '@/shared/api/errors';
import { reportFailure, withFieldRoots } from '@/shared/api/formFailure';
import type { AlertChannel } from '@/shared/api/resources';
import { useValidationTexts } from '@/shared/i18n/useValidationTexts';
import { Button } from '@/shared/ui/button';
import { SwitchField, TextareaField } from '@/shared/ui/choice-fields';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';

interface ChannelFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  organizationId: string;
  channel: AlertChannel | null;
  alerting: AlertingMeta;
}

const fields = ['type', 'name', 'recipients', 'webhook_url', 'is_enabled'] as const;

function useChannelFormTexts() {
  const { t } = useTranslation(['alerting']);
  const base = useValidationTexts();
  return useMemo(
    () => ({
      ...base,
      maxLength: (max: number) => t('alerting:form.errors.max_length', { max }),
      recipientCount: (bounds: { min: number; max: number }) => t('alerting:form.errors.recipient_count', { ...bounds }),
      recipient: (address: string) => t('alerting:form.errors.recipient', { address }),
      webhook: t('alerting:form.errors.webhook'),
    }),
    [base, t],
  );
}

function ChannelForm({ organizationId, channel, alerting, onOpenChange }: Omit<ChannelFormDialogProps, 'open'>) {
  const { t } = useTranslation(['alerting', 'errors']);
  const texts = useChannelFormTexts();
  const create = useCreateChannel(organizationId);
  const update = useUpdateChannel(organizationId);
  const mode = channel === null ? 'create' : 'edit';
  const schema = useMemo(() => channelFormSchema(texts, alerting, mode), [texts, alerting, mode]);
  const form = useForm<ChannelFormValues>({ resolver: zodResolver(schema), defaultValues: channelFormValues(channel, 'email') });
  const type = useWatch({ control: form.control, name: 'type' });
  const { errors, isSubmitting } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    try {
      if (channel === null) {
        await create.mutateAsync(createChannelBody(values));
      } else {
        const body = updateChannelBody(channel, values);
        if (Object.keys(body).length > 0) {
          await update.mutateAsync({ channelId: channel.id, body });
        }
      }
      onOpenChange(false);
    } catch (error) {
      reportFailure(form.setError, withFieldRoots(failureOf(error)), fields, (key) => t(key));
    }
  });

  return (
    <form noValidate onSubmit={(event) => void submit(event)} className="grid gap-5">
      {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
      {channel === null ? (
        <Controller
          control={form.control}
          name="type"
          render={({ field }) => (
            <div role="radiogroup" aria-label={t('alerting:form.type')} className="flex gap-[5px]">
              {channelTypes.map((candidate) => (
                <button
                  key={candidate}
                  type="button"
                  role="radio"
                  aria-checked={field.value === candidate}
                  onClick={() => {
                    field.onChange(candidate);
                  }}
                  className="h-10 rounded-[22px] md:h-9 px-[17px] text-base text-muted-foreground aria-checked:bg-bright aria-checked:text-ink"
                >
                  {t(channelTypeTextKeys[candidate])}
                </button>
              ))}
            </div>
          )}
        />
      ) : null}
      <FormField label={t('alerting:form.name')} autoComplete="off" error={errors.name?.message} {...form.register('name')} />
      {type === 'email' ? (
        <TextareaField
          label={t('alerting:form.recipients')}
          hint={t('alerting:form.recipients_hint', { max: alerting.email_recipients.max })}
          error={errors.recipients?.message}
          {...form.register('recipients')}
        />
      ) : (
        <FormField
          label={t('alerting:form.webhook_url')}
          type="url"
          inputMode="url"
          autoComplete="off"
          spellCheck={false}
          placeholder={t(type === 'discord' ? 'alerting:form.discord_placeholder' : 'alerting:form.slack_placeholder')}
          hint={
            channel?.webhook_hint === null || channel === null
              ? t('alerting:form.webhook_hint')
              : t('alerting:form.webhook_kept', { host: channel.webhook_hint.host, last_four: channel.webhook_hint.last_four })
          }
          error={errors.webhook_url?.message}
          {...form.register('webhook_url')}
        />
      )}
      <Controller
        control={form.control}
        name="is_enabled"
        render={({ field }) => <SwitchField label={t('alerting:form.enabled')} description={t('alerting:form.enabled_hint')} checked={field.value} onCheckedChange={field.onChange} />}
      />
      <DialogFooter className="gap-2">
        <Button
          type="button"
          variant="outline"
          className="h-10 rounded-[9px]"
          onClick={() => {
            onOpenChange(false);
          }}
        >
          {t('alerting:form.cancel')}
        </Button>
        <Button type="submit" disabled={isSubmitting} aria-busy={isSubmitting} className="h-10 rounded-[9px]">
          {channel === null ? t('alerting:form.create') : t('alerting:form.save')}
        </Button>
      </DialogFooter>
    </form>
  );
}

export function ChannelFormDialog({ open, ...props }: ChannelFormDialogProps) {
  const { t } = useTranslation(['alerting']);
  return (
    <Dialog open={open} onOpenChange={props.onOpenChange}>
      <DialogContent className="max-h-[92svh] overflow-y-auto rounded-[20px] border-2 border-line-strong bg-panel p-[25px] ring-0 sm:max-w-[540px]">
        <DialogHeader>
          <DialogTitle className="text-[23px] font-medium">
            {props.channel === null ? t('alerting:form.title_create') : t('alerting:form.title_edit', { name: props.channel.name })}
          </DialogTitle>
          <DialogDescription>{t('alerting:form.description')}</DialogDescription>
        </DialogHeader>
        {open ? <ChannelForm key={props.channel?.id ?? 'new'} {...props} /> : null}
      </DialogContent>
    </Dialog>
  );
}
