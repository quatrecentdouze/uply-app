import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { type ApiClient, expectData } from '@/shared/api/client';
import type { RealtimeConnection } from '@/shared/api/meta';

export interface RealtimeChannel {
  listen(event: string, callback: (payload: unknown) => void): unknown;
}

export interface RealtimeSocket {
  private(channel: string): RealtimeChannel;
  leave(channel: string): void;
  disconnect(): void;
}

export function createEchoSocket(connection: RealtimeConnection, client: ApiClient): RealtimeSocket {
  return new Echo<'reverb'>({
    broadcaster: 'reverb',
    Pusher,
    key: connection.key,
    wsHost: connection.host,
    wsPort: connection.port,
    wssPort: connection.port,
    forceTLS: connection.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    channelAuthorization: {
      customHandler: ({ socketId, channelName }, callback) => {
        expectData(client.POST('/broadcasting/auth', { body: { socket_id: socketId, channel_name: channelName } })).then(
          (authorization) => {
            callback(null, authorization);
          },
          (error: unknown) => {
            callback(error instanceof Error ? error : new Error(String(error)), null);
          },
        );
      },
    },
  });
}
