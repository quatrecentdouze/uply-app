export type LiveStatus = 'off' | 'connecting' | 'live' | 'paused';

const pausedStates: readonly string[] = ['unavailable', 'failed'];

export function liveStatusOf(connectionState: string): LiveStatus {
  if (connectionState === 'connected') {
    return 'live';
  }
  return pausedStates.includes(connectionState) ? 'paused' : 'connecting';
}

export function connectionStateOf(change: unknown): string | null {
  if (typeof change !== 'object' || change === null || !('current' in change)) {
    return null;
  }
  return typeof change.current === 'string' ? change.current : null;
}
