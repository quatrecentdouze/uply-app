import { Link } from '@tanstack/react-router';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { channelStatus, channelStatusTextKeys, channelTypeTextKeys, sortedChannels, toggledChannel } from '@/features/alerting/model/channels';
import type { AlertChannel } from '@/shared/api/resources';
import { Checkbox } from '@/shared/ui/checkbox';
import { Label } from '@/shared/ui/label';
import { Skeleton } from '@/shared/ui/skeleton';
import { Pill } from '@/shared/ui/surface-card';

interface ChannelPickerProps {
  channels: readonly AlertChannel[] | undefined;
  selected: readonly string[];
  onChange: (selected: string[]) => void;
  disabled?: boolean;
}

function ChannelOption({ channel, checked, onCheckedChange, disabled }: { channel: AlertChannel; checked: boolean; onCheckedChange: (checked: boolean) => void; disabled: boolean }) {
  const { t } = useTranslation(['alerting']);
  const id = useId();
  const status = channelStatus(channel);
  return (
    <li className="flex items-center gap-3 rounded-[11px] border-2 border-divider bg-surface-raised px-3.5 py-2.5">
      <Checkbox
        id={id}
        checked={checked}
        disabled={disabled}
        onCheckedChange={(value) => {
          onCheckedChange(value === true);
        }}
      />
      <Label htmlFor={id} className="flex min-w-0 flex-1 items-center gap-2 text-base font-normal">
        <span className="truncate">{channel.name}</span>
        <span className="text-sm text-muted-foreground">{t(channelTypeTextKeys[channel.type])}</span>
      </Label>
      {status === 'enabled' ? null : <Pill tone={status === 'rejected' ? 'alert' : 'paused'}>{t(channelStatusTextKeys[status])}</Pill>}
    </li>
  );
}

export function ChannelPicker({ channels, selected, onChange, disabled = false }: ChannelPickerProps) {
  const { t } = useTranslation(['alerting']);
  return (
    <fieldset className="grid gap-2">
      <legend className="mb-2 text-sm text-muted-foreground">{t('alerting:picker.label')}</legend>
      {channels === undefined ? (
        <Skeleton className="h-12 w-full" />
      ) : channels.length === 0 ? (
        <p className="text-sm text-faint">
          {t('alerting:picker.empty')}{' '}
          <Link to="/alert-channels" className="text-primary hover:underline">
            {t('alerting:picker.create')}
          </Link>
        </p>
      ) : (
        <ul className="grid gap-2">
          {sortedChannels(channels).map((channel) => (
            <ChannelOption
              key={channel.id}
              channel={channel}
              disabled={disabled}
              checked={selected.includes(channel.id)}
              onCheckedChange={(checked) => {
                onChange(toggledChannel(selected, channel.id, checked));
              }}
            />
          ))}
        </ul>
      )}
    </fieldset>
  );
}
