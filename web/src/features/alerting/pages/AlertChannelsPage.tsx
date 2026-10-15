import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useAlertChannels, useDeleteChannel, useTestChannel } from '@/features/alerting/api/alertChannels';
import { ChannelFormDialog } from '@/features/alerting/components/ChannelFormDialog';
import type { AlertingMeta } from '@/features/alerting/model/channelForm';
import { channelStatus, channelStatusTextKeys, channelSummary, channelTypeTextKeys, sortedChannels } from '@/features/alerting/model/channels';
import { errorTextKey, failureOf } from '@/shared/api/errors';
import type { AlertChannel, ChannelType } from '@/shared/api/resources';
import { Button } from '@/shared/ui/button';
import { ConfirmDialog } from '@/shared/ui/confirm-dialog';
import { FormAlert } from '@/shared/ui/form-alert';
import { Hash, type LucideIcon, Mail, MessageSquare, Pencil, Plus, Send, Trash2 } from '@/shared/ui/icons';
import { Skeleton } from '@/shared/ui/skeleton';
import { Pill, SurfaceCard } from '@/shared/ui/surface-card';
import { useConfirmAction } from '@/shared/ui/use-confirm-action';
import { retryOf } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';

interface AlertChannelsPageProps {
  organizationId: string;
  alerting: AlertingMeta | null;
  canManage: boolean;
}

const typeIcons: Record<ChannelType, LucideIcon> = {
  email: Mail,
  discord: MessageSquare,
  slack: Hash,
};

type TestNotice = { tone: 'positive' | 'alert'; text: string };

interface ChannelCardProps {
  channel: AlertChannel;
  manage: { onEdit: () => void; onDelete: () => void; onTest: () => void; testing: boolean } | null;
  notice: TestNotice | null;
}

function ChannelCard({ channel, manage, notice }: ChannelCardProps) {
  const { t } = useTranslation(['alerting']);
  const Icon = typeIcons[channel.type];
  const status = channelStatus(channel);
  const summary = channelSummary(channel);
  return (
    <li>
      <SurfaceCard aria-label={channel.name} className="flex h-full flex-col gap-4 p-4 sm:p-5">
        <div className="flex items-start gap-3">
          <span aria-hidden="true" className="grid size-[46px] shrink-0 place-items-center rounded-[14px] bg-chip text-primary">
            <Icon className="size-6" />
          </span>
          <div className="grid min-w-0 flex-1 gap-0.5">
            <h2 className="truncate text-lg font-normal">{channel.name}</h2>
            <p className="text-sm text-muted-foreground">{t(channelTypeTextKeys[channel.type])}</p>
          </div>
          <Pill tone={status === 'enabled' ? 'positive' : status === 'rejected' ? 'alert' : 'paused'}>{t(channelStatusTextKeys[status])}</Pill>
        </div>
        <div className="min-h-10 rounded-[11px] bg-chip px-3.5 py-2.5 text-sm text-muted-foreground">
          {summary.kind === 'recipients' ? (
            <p className="break-words">{summary.recipients.join(', ')}</p>
          ) : summary.kind === 'webhook' ? (
            <p>{t('alerting:card.webhook', { host: summary.host, last_four: summary.lastFour })}</p>
          ) : null}
        </div>
        {notice === null ? null : <FormAlert tone={notice.tone}>{notice.text}</FormAlert>}
        {manage === null ? null : (
          <div className="mt-auto flex flex-wrap gap-2">
            <Button type="button" variant="outline" className="h-9 rounded-[10px]" disabled={manage.testing} aria-busy={manage.testing} onClick={manage.onTest}>
              <Send aria-hidden="true" />
              {t('alerting:card.test')}
            </Button>
            <Button type="button" variant="outline" className="h-10 rounded-[10px] md:h-9" onClick={manage.onEdit}>
              <Pencil aria-hidden="true" />
              {t('alerting:card.edit')}
            </Button>
            <Button type="button" variant="destructive" className="ml-auto h-10 rounded-[10px] md:h-9" onClick={manage.onDelete}>
              <Trash2 aria-hidden="true" />
              {t('alerting:card.delete')}
            </Button>
          </div>
        )}
      </SurfaceCard>
    </li>
  );
}

interface EditorState {
  open: boolean;
  channel: AlertChannel | null;
}

export function AlertChannelsPage({ organizationId, alerting, canManage }: AlertChannelsPageProps) {
  const { t } = useTranslation(['alerting', 'errors']);
  const query = useAlertChannels(organizationId);
  const channels = query.data;
  const retry = retryOf(query);
  const remove = useDeleteChannel(organizationId);
  const test = useTestChannel(organizationId);
  const [editor, setEditor] = useState<EditorState>({ open: false, channel: null });
  const [notices, setNotices] = useState<Readonly<Record<string, TestNotice>>>({});
  const deletion = useConfirmAction(async (channel: AlertChannel) => {
    await remove.mutateAsync(channel.id);
  });
  const writable = canManage && alerting !== null;

  const sendTest = (channel: AlertChannel) => {
    test.mutate(channel.id, {
      onSuccess: () => {
        setNotices((current) => ({ ...current, [channel.id]: { tone: 'positive', text: t('alerting:card.test_sent') } }));
      },
      onError: (error) => {
        setNotices((current) => ({ ...current, [channel.id]: { tone: 'alert', text: t(errorTextKey(failureOf(error))) } }));
      },
    });
  };

  return (
    <div className="grid gap-4 2xl:gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-muted-foreground">{t('alerting:page.description')}</p>
        {writable ? (
          <Button
            type="button"
            className="h-10 rounded-[11px] px-4"
            onClick={() => {
              setEditor({ open: true, channel: null });
            }}
          >
            <Plus aria-hidden="true" />
            {t('alerting:page.add')}
          </Button>
        ) : null}
      </div>
      {channels === undefined ? (
        retry === null ? (
          <Skeleton className="h-[200px] w-full" />
        ) : (
          <LoadError onRetry={retry} />
        )
      ) : channels.length === 0 ? (
        <SurfaceCard className="p-8 text-center text-muted-foreground">{t('alerting:page.empty')}</SurfaceCard>
      ) : (
        <ul aria-label={t('alerting:page.list')} className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3 2xl:gap-5">
          {sortedChannels(channels).map((channel) => (
            <ChannelCard
              key={channel.id}
              channel={channel}
              notice={notices[channel.id] ?? null}
              manage={
                writable
                  ? {
                      testing: test.isPending && test.variables === channel.id,
                      onTest: () => {
                        sendTest(channel);
                      },
                      onEdit: () => {
                        setEditor({ open: true, channel });
                      },
                      onDelete: () => {
                        deletion.ask(channel);
                      },
                    }
                  : null
              }
            />
          ))}
        </ul>
      )}
      {alerting === null ? null : (
        <ChannelFormDialog
          open={editor.open}
          onOpenChange={(open) => {
            setEditor((current) => ({ ...current, open }));
          }}
          organizationId={organizationId}
          channel={editor.channel}
          alerting={alerting}
        />
      )}
      <ConfirmDialog
        {...deletion.dialog}
        texts={{
          title: t('alerting:delete.title', { name: deletion.target?.name ?? '' }),
          description: t('alerting:delete.description'),
          confirm: t('alerting:delete.confirm'),
          cancel: t('alerting:delete.cancel'),
        }}
      />
    </div>
  );
}
