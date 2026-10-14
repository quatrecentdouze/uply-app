import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import type { ApiClient } from '@/shared/api/client';
import type { RealtimeConnection } from '@/shared/api/meta';
import { applyRealtimeEvent } from '@/shared/realtime/cacheUpdates';
import { organizationChannel, parseRealtimeEvent, realtimeEventNames } from '@/shared/realtime/events';
import { createEchoSocket, type RealtimeSocket } from '@/shared/realtime/socket';
import { type LiveStatus, liveStatusOf } from '@/shared/realtime/status';

export type SocketFactory = (connection: RealtimeConnection, client: ApiClient) => RealtimeSocket;

export interface OrganizationRealtimeOptions {
  connection: RealtimeConnection | null;
  client: ApiClient;
  organizationId: string | null;
  createSocket?: SocketFactory;
}

export function useOrganizationRealtime({ connection, client, organizationId, createSocket = createEchoSocket }: OrganizationRealtimeOptions): LiveStatus {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<LiveStatus>('connecting');

  useEffect(() => {
    if (connection === null || organizationId === null) {
      return;
    }
    const socket = createSocket(connection, client);
    let active = true;
    socket.onStateChange((state) => {
      if (active) {
        setStatus(liveStatusOf(state));
      }
    });
    const name = organizationChannel(organizationId);
    const channel = socket.private(name);
    for (const eventName of realtimeEventNames) {
      channel.listen(`.${eventName}`, (payload) => {
        const event = parseRealtimeEvent(eventName, payload);
        if (event !== null) {
          applyRealtimeEvent(queryClient, organizationId, event);
        }
      });
    }
    return () => {
      active = false;
      socket.leave(name);
      socket.disconnect();
    };
  }, [connection, client, organizationId, createSocket, queryClient]);

  if (connection === null || organizationId === null) {
    return 'off';
  }
  return status;
}
