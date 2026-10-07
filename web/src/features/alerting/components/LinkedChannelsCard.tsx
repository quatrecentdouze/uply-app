import { useTranslation } from 'react-i18next';
import { useMonitorChannels } from '@/features/alerting/api/alertChannels';
import { channelStatus, channelStatusTextKeys, channelTypeTextKeys, sortedChannels } from '@/features/alerting/model/channels';
import { Skeleton } from '@/shared/ui/skeleton';
import { CardHeading, Pill, SurfaceCard } from '@/shared/ui/surface-card';

export function LinkedChannelsCard({ organizationId, monitorId }: { organizationId: string; monitorId: string }) {
  const { t } = useTranslation(['alerting']);
  const channels = useMonitorChannels(organizationId, monitorId).data;
  return (
    <SurfaceCard aria-labelledby="linked-channels-title" className="p-4 sm:p-5">
      <CardHeading id="linked-channels-title" title={t('alerting:linked.title')} info={t('alerting:linked.info')} />
      <div className="mt-3">
        {channels === undefined ? (
          <Skeleton className="h-[60px] w-full" />
        ) : channels.length === 0 ? (
          <p className="py-2 text-muted-foreground">{t('alerting:linked.empty')}</p>
        ) : (
          <ul className="grid gap-2">
            {sortedChannels(channels).map((channel) => {
              const status = channelStatus(channel);
              return (
                <li key={channel.id} className="flex items-center gap-3 rounded-[13px] border-2 border-divider bg-surface-raised px-3.5 py-2.5">
                  <span className="min-w-0 flex-1 truncate">{channel.name}</span>
                  <span className="text-sm text-muted-foreground">{t(channelTypeTextKeys[channel.type])}</span>
                  {status === 'enabled' ? null : <Pill tone={status === 'rejected' ? 'alert' : 'paused'}>{t(channelStatusTextKeys[status])}</Pill>}
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </SurfaceCard>
  );
}
