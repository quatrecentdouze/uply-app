import { useState } from 'react';
import { useAlertChannels, useLinkChannels, useMonitorChannels } from '@/features/alerting/api/alertChannels';
import { ChannelPicker } from '@/features/alerting/components/ChannelPicker';
import { MonitorFormDialog } from '@/features/monitors/components/MonitorFormDialog';
import type { MonitoringMeta } from '@/features/monitors/model/monitorForm';
import type { Monitor } from '@/shared/api/resources';

interface MonitorEditorProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  organizationId: string;
  monitor: Monitor | null;
  monitoring: MonitoringMeta;
  onCreated?: (monitor: Monitor) => void;
}

function sameSelection(left: readonly string[], right: readonly string[]): boolean {
  return left.length === right.length && left.every((id) => right.includes(id));
}

function LinkedChannelsField({ linked, onChange, organizationId }: { linked: readonly string[] | undefined; onChange: (ids: string[]) => void; organizationId: string }) {
  const channels = useAlertChannels(organizationId).data;
  const [selected, setSelected] = useState<readonly string[] | null>(null);
  const shown = selected ?? linked ?? [];
  return (
    <ChannelPicker
      channels={linked === undefined ? undefined : channels}
      selected={shown}
      onChange={(ids) => {
        setSelected(ids);
        onChange(ids);
      }}
    />
  );
}

export function MonitorEditor({ open, onOpenChange, organizationId, monitor, monitoring, onCreated }: MonitorEditorProps) {
  const linked = useMonitorChannels(organizationId, open ? (monitor?.id ?? null) : null).data;
  const link = useLinkChannels(organizationId);
  const [chosen, setChosen] = useState<string[] | null>(null);
  const current = monitor === null ? [] : linked?.map((channel) => channel.id);

  const close = (next: boolean) => {
    if (!next) {
      setChosen(null);
    }
    onOpenChange(next);
  };

  const onSaved = async (saved: Monitor) => {
    try {
      if (chosen !== null && !sameSelection(chosen, current ?? [])) {
        await link.mutateAsync({ monitorId: saved.id, channelIds: chosen });
      }
    } finally {
      if (monitor === null) {
        onCreated?.(saved);
      }
    }
  };

  return (
    <MonitorFormDialog
      open={open}
      onOpenChange={close}
      organizationId={organizationId}
      monitor={monitor}
      monitoring={monitoring}
      onSaved={onSaved}
      extra={open ? <LinkedChannelsField key={monitor?.id ?? 'new'} linked={current} onChange={setChosen} organizationId={organizationId} /> : null}
    />
  );
}
