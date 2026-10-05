import { describe, expect, it } from 'vitest';
import { channelStatus, channelSummary, sortedChannels, toggledChannel } from '@/features/alerting/model/channels';
import { alertChannelFixture } from '@/shared/testing/fixtures';

describe('channelSummary', () => {
  it('lists the recipients of an email channel', () => {
    expect(channelSummary(alertChannelFixture({ type: 'email', recipients: ['ops@example.test'], webhook_hint: null }))).toEqual({
      kind: 'recipients',
      recipients: ['ops@example.test'],
    });
  });

  it('shows only the hint of a webhook, never the address', () => {
    expect(channelSummary(alertChannelFixture({ type: 'discord', recipients: null, webhook_hint: { host: 'discord.com', last_four: 'a1b2' } }))).toEqual({
      kind: 'webhook',
      host: 'discord.com',
      lastFour: 'a1b2',
    });
  });
});

describe('channelStatus', () => {
  it('tells a channel turned off from one the provider rejected', () => {
    expect(channelStatus({ is_enabled: true, disabled_reason: null })).toBe('enabled');
    expect(channelStatus({ is_enabled: false, disabled_reason: null })).toBe('disabled');
    expect(channelStatus({ is_enabled: false, disabled_reason: 'webhook_rejected' })).toBe('rejected');
  });
});

describe('channel selection', () => {
  it('adds and removes a channel once', () => {
    expect(toggledChannel(['a'], 'b', true)).toEqual(['a', 'b']);
    expect(toggledChannel(['a', 'b'], 'b', true)).toEqual(['a', 'b']);
    expect(toggledChannel(['a', 'b'], 'a', false)).toEqual(['b']);
  });

  it('sorts the channels by name', () => {
    const channels = [alertChannelFixture({ id: '2', name: 'Slack ops' }), alertChannelFixture({ id: '1', name: 'Email team' })];

    expect(sortedChannels(channels).map((channel) => channel.id)).toEqual(['1', '2']);
  });
});
