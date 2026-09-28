import type { RealtimeChannel, RealtimeSocket } from '@/shared/realtime/socket';

export interface FakeSocket extends RealtimeSocket {
  joined: string[];
  left: string[];
  disconnected: boolean;
  emit(channel: string, event: string, payload: unknown): void;
}

export function fakeSocket(): FakeSocket {
  const listeners = new Map<string, Map<string, (payload: unknown) => void>>();
  const socket: FakeSocket = {
    joined: [],
    left: [],
    disconnected: false,
    private(name): RealtimeChannel {
      socket.joined.push(name);
      const channelListeners = new Map<string, (payload: unknown) => void>();
      listeners.set(name, channelListeners);
      return {
        listen(event, callback) {
          channelListeners.set(event, callback);
          return this;
        },
      };
    },
    leave(name) {
      socket.left.push(name);
      listeners.delete(name);
    },
    disconnect() {
      socket.disconnected = true;
    },
    emit(channel, event, payload) {
      listeners.get(channel)?.get(event)?.(payload);
    },
  };
  return socket;
}
