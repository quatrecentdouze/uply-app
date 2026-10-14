import { describe, expect, it } from 'vitest';
import { connectionStateOf, liveStatusOf } from '@/shared/realtime/status';

describe('liveStatusOf', () => {
  it('pauses live updates only while the connection is unavailable or failed', () => {
    expect(liveStatusOf('connected')).toBe('live');
    expect(liveStatusOf('unavailable')).toBe('paused');
    expect(liveStatusOf('failed')).toBe('paused');
    expect(liveStatusOf('connecting')).toBe('connecting');
    expect(liveStatusOf('initialized')).toBe('connecting');
  });
});

describe('connectionStateOf', () => {
  it('reads the current state of a Pusher state change', () => {
    expect(connectionStateOf({ previous: 'connecting', current: 'unavailable' })).toBe('unavailable');
    expect(connectionStateOf({ current: 4 })).toBeNull();
    expect(connectionStateOf(null)).toBeNull();
  });
});
