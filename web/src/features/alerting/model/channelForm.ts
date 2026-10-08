import { z } from 'zod';
import type { CreateChannelBody, UpdateChannelBody } from '@/features/alerting/api/alertChannels';
import type { Meta } from '@/shared/api/meta';
import type { AlertChannel, ChannelType } from '@/shared/api/resources';
import type { BaseValidationTexts } from '@/shared/i18n/useValidationTexts';

export type AlertingMeta = Meta['alerting'];

export interface ChannelFormTexts extends BaseValidationTexts {
  maxLength: (max: number) => string;
  recipientCount: (bounds: { min: number; max: number }) => string;
  recipient: (address: string) => string;
  webhook: string;
}

export interface ChannelFormValues {
  type: ChannelType;
  name: string;
  recipients: string;
  webhook_url: string;
  is_enabled: boolean;
}

export type ChannelFormMode = 'create' | 'edit';

export function recipientsOf(text: string): string[] {
  const addresses = text
    .split(/[\s,;]+/)
    .map((address) => address.trim().toLowerCase())
    .filter((address) => address !== '');
  return [...new Set(addresses)];
}

const emailCheck = z.email();

function isHttpsUrl(value: string): boolean {
  return URL.canParse(value) && new URL(value).protocol === 'https:';
}

export function channelFormSchema(texts: ChannelFormTexts, alerting: AlertingMeta, mode: ChannelFormMode) {
  const { lengths, email_recipients: bounds } = alerting;
  return z
    .object({
      type: z.enum(['email', 'discord', 'slack']),
      name: z.string().trim().min(1, texts.required).max(lengths.channel_name.max, texts.maxLength(lengths.channel_name.max)),
      recipients: z.string(),
      webhook_url: z.string().trim().max(lengths.webhook_url.max, texts.maxLength(lengths.webhook_url.max)),
      is_enabled: z.boolean(),
    })
    .superRefine((values, context) => {
      if (values.type === 'email') {
        const addresses = recipientsOf(values.recipients);
        const wrong = addresses.find((address) => !emailCheck.safeParse(address).success);
        if (wrong !== undefined) {
          context.addIssue({ code: 'custom', path: ['recipients'], message: texts.recipient(wrong) });
        } else if (addresses.length < bounds.min || addresses.length > bounds.max) {
          context.addIssue({ code: 'custom', path: ['recipients'], message: texts.recipientCount(bounds) });
        }
        return;
      }
      if (values.webhook_url === '') {
        if (mode === 'create') {
          context.addIssue({ code: 'custom', path: ['webhook_url'], message: texts.required });
        }
        return;
      }
      if (!isHttpsUrl(values.webhook_url)) {
        context.addIssue({ code: 'custom', path: ['webhook_url'], message: texts.webhook });
      }
    });
}

export function channelFormValues(channel: AlertChannel | null, type: ChannelType): ChannelFormValues {
  return {
    type: channel?.type ?? type,
    name: channel?.name ?? '',
    recipients: channel?.recipients?.join(', ') ?? '',
    webhook_url: '',
    is_enabled: channel?.is_enabled ?? true,
  };
}

export function createChannelBody(values: ChannelFormValues): CreateChannelBody {
  const common = { type: values.type, name: values.name.trim(), is_enabled: values.is_enabled };
  return values.type === 'email' ? { ...common, recipients: recipientsOf(values.recipients) } : { ...common, webhook_url: values.webhook_url.trim() };
}

function sameList(left: readonly string[], right: readonly string[]): boolean {
  return left.length === right.length && left.every((item, index) => item === right[index]);
}

export function updateChannelBody(channel: AlertChannel, values: ChannelFormValues): UpdateChannelBody {
  const name = values.name.trim();
  const recipients = recipientsOf(values.recipients);
  const webhook = values.webhook_url.trim();
  return {
    ...(name === channel.name ? {} : { name }),
    ...(values.is_enabled === channel.is_enabled ? {} : { is_enabled: values.is_enabled }),
    ...(channel.type === 'email' && !sameList(recipients, channel.recipients ?? []) ? { recipients } : {}),
    ...(channel.type !== 'email' && webhook !== '' ? { webhook_url: webhook } : {}),
  };
}
