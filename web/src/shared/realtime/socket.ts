import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { type ApiClient, expectData } from '@/shared/api/client';
import type { RealtimeConnection } from '@/shared/api/meta';
import { connectionStateOf } from '@/shared/realtime/status';

export interface RealtimeChannel {
  listen(event: string, callback: (payload: unknown) => void): unknown;
}

export interface RealtimeSocket {
  private(channel: string): RealtimeChannel;
  leave(channel: string): void;
  disconnect(): void;
  onStateChange(listener: (state: string) => void): void;
}

export function createEchoSocket(connection: RealtimeConnection, client: ApiClient): RealtimeSocket {
  const echo = new Echo<'reverb'>({
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
  return {
    private: (channel) => echo.private(channel),
    leave: (channel) => {
      echo.leave(channel);
    },
    disconnect: () => {
      echo.disconnect();
    },
    onStateChange: (listener) => {
      echo.connector.pusher.connection.bind('state_change', (change: unknown) => {
        const state = connectionStateOf(change);
        if (state !== null) {
          listener(state);
        }
      });
    },
  };
}
