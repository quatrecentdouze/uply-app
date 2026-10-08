import { describe, expect, it } from 'vitest';
import {
  type ChannelFormMode,
  type ChannelFormTexts,
  type ChannelFormValues,
  channelFormSchema,
  channelFormValues,
  createChannelBody,
  recipientsOf,
  updateChannelBody,
} from '@/features/alerting/model/channelForm';
import { alertChannelFixture, metaFixture } from '@/shared/testing/fixtures';

const alerting = metaFixture().alerting;

const texts: ChannelFormTexts = {
  required: 'required',
  email: 'email',
  length: () => 'length',
  mismatch: 'mismatch',
  maxLength: (max) => `max ${String(max)}`,
  recipientCount: ({ min, max }) => `count ${String(min)}-${String(max)}`,
  recipient: (address) => `bad ${address}`,
  webhook: 'webhook',
};

function issues(values: Partial<ChannelFormValues>, mode: ChannelFormMode = 'create') {
  const result = channelFormSchema(texts, alerting, mode).safeParse({ ...channelFormValues(null, 'email'), name: 'Ops', ...values });
  return Object.fromEntries((result.error?.issues ?? []).map((issue) => [issue.path.join('.'), issue.message]));
}

describe('recipientsOf', () => {
  it('splits, lowercases and dedupes the addresses', () => {
    expect(recipientsOf('Ops@Example.test, dev@example.test;\nops@example.test ')).toEqual(['ops@example.test', 'dev@example.test']);
  });
});

describe('channelFormSchema', () => {
  it('needs 1 to 10 valid recipients for an email channel', () => {
    expect(issues({ recipients: 'ops@example.test' })).toEqual({});
    expect(issues({ recipients: '' })).toEqual({ recipients: 'count 1-10' });
    expect(issues({ recipients: 'ops@example.test, nope' })).toEqual({ recipients: 'bad nope' });
    expect(issues({ recipients: Array.from({ length: 11 }, (_, index) => `ops${String(index)}@example.test`).join(',') })).toEqual({ recipients: 'count 1-10' });
  });

  it('needs an https webhook to create a Discord or Slack channel', () => {
    expect(issues({ type: 'discord', webhook_url: '' })).toEqual({ webhook_url: 'required' });
    expect(issues({ type: 'slack', webhook_url: 'http://hooks.slack.com/services/x' })).toEqual({ webhook_url: 'webhook' });
    expect(issues({ type: 'slack', webhook_url: 'https://hooks.slack.com/services/x' })).toEqual({});
  });

  it('keeps the stored webhook when the edit leaves it empty', () => {
    expect(issues({ type: 'discord', webhook_url: '' }, 'edit')).toEqual({});
  });

  it('bounds the name', () => {
    expect(issues({ name: ' ', recipients: 'ops@example.test' })).toEqual({ name: 'required' });
    expect(issues({ name: 'x'.repeat(81), recipients: 'ops@example.test' })).toEqual({ name: 'max 80' });
  });
});

describe('channel bodies', () => {
  it('sends the field of the chosen type only', () => {
    expect(createChannelBody({ ...channelFormValues(null, 'email'), name: 'Ops', recipients: 'ops@example.test', webhook_url: 'https://ignored.test' })).toEqual({
      type: 'email',
      name: 'Ops',
      is_enabled: true,
      recipients: ['ops@example.test'],
    });
    expect(createChannelBody({ ...channelFormValues(null, 'discord'), name: 'Ops', webhook_url: ' https://discord.com/api/webhooks/1/abc ' })).toEqual({
      type: 'discord',
      name: 'Ops',
      is_enabled: true,
      webhook_url: 'https://discord.com/api/webhooks/1/abc',
    });
  });

  it('never fills the webhook field with the stored address', () => {
    const channel = alertChannelFixture({ type: 'slack', recipients: null, webhook_hint: { host: 'hooks.slack.com', last_four: 'wxyz' } });

    expect(channelFormValues(channel, 'email').webhook_url).toBe('');
  });

  it('patches only the changed fields and never the type', () => {
    const email = alertChannelFixture({ recipients: ['ops@example.test'] });
    const slack = alertChannelFixture({ type: 'slack', recipients: null, webhook_hint: { host: 'hooks.slack.com', last_four: 'wxyz' } });

    expect(updateChannelBody(email, channelFormValues(email, 'email'))).toEqual({});
    expect(updateChannelBody(email, { ...channelFormValues(email, 'email'), recipients: 'ops@example.test, dev@example.test', is_enabled: false })).toEqual({
      is_enabled: false,
      recipients: ['ops@example.test', 'dev@example.test'],
    });
    expect(updateChannelBody(slack, { ...channelFormValues(slack, 'email'), webhook_url: 'https://hooks.slack.com/services/new' })).toEqual({
      webhook_url: 'https://hooks.slack.com/services/new',
    });
  });
});
