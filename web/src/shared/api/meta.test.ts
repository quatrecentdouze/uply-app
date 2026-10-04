import { describe, expect, it } from 'vitest';
import { realtimeConnection } from '@/shared/api/meta';
import { metaFixture } from '@/shared/testing/fixtures';

const realtime = { key: 'public-key', host: 'localhost', port: 6001, scheme: 'http' };

describe('realtimeConnection', () => {
  it('reads the connection of the realtime section', () => {
    expect(realtimeConnection(metaFixture({ realtime }))).toEqual(realtime);
  });

  it('turns off real time without an app key', () => {
    expect(realtimeConnection(metaFixture({ realtime: { ...realtime, key: null } }))).toBeNull();
    expect(realtimeConnection(metaFixture({ realtime: { ...realtime, key: '' } }))).toBeNull();
  });
});
