import type { AlertChannel, ChannelType } from '@/shared/api/resources';
import type { TextKey } from '@/shared/i18n/textKey';

export const channelTypes: readonly ChannelType[] = ['email', 'discord', 'slack'];

export const channelTypeTextKeys: Record<ChannelType, TextKey<'alerting'>> = {
  email: 'alerting:types.email',
  discord: 'alerting:types.discord',
  slack: 'alerting:types.slack',
};

export type ChannelSummary =
  | { kind: 'recipients'; recipients: readonly string[] }
  | { kind: 'webhook'; host: string; lastFour: string }
  | { kind: 'none' };

export function channelSummary(channel: Pick<AlertChannel, 'recipients' | 'webhook_hint'>): ChannelSummary {
  if (channel.recipients !== null) {
    return { kind: 'recipients', recipients: channel.recipients };
  }
  if (channel.webhook_hint !== null) {
    return { kind: 'webhook', host: channel.webhook_hint.host, lastFour: channel.webhook_hint.last_four };
  }
  return { kind: 'none' };
}

export type ChannelStatus = 'enabled' | 'disabled' | 'rejected';

export function channelStatus(channel: Pick<AlertChannel, 'is_enabled' | 'disabled_reason'>): ChannelStatus {
  if (channel.is_enabled) {
    return 'enabled';
  }
  return channel.disabled_reason === 'webhook_rejected' ? 'rejected' : 'disabled';
}

export const channelStatusTextKeys: Record<ChannelStatus, TextKey<'alerting'>> = {
  enabled: 'alerting:status.enabled',
  disabled: 'alerting:status.disabled',
  rejected: 'alerting:status.rejected',
};

export function toggledChannel(selected: readonly string[], channelId: string, checked: boolean): string[] {
  const others = selected.filter((id) => id !== channelId);
  return checked ? [...others, channelId] : others;
}

export function sortedChannels(channels: readonly AlertChannel[]): AlertChannel[] {
  return [...channels].sort((left, right) => left.name.localeCompare(right.name));
}
