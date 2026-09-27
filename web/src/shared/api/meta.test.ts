import { describe, expect, it } from 'vitest';
import { parseMeta } from '@/shared/api/meta';

const realtime = { key: 'public-key', host: 'localhost', port: 6001, scheme: 'http' };

describe('parseMeta', () => {
  it('reads the realtime connection', () => {
    expect(parseMeta({ data: { api: {}, realtime } })).toEqual({ realtime });
  });

  it('turns off real time without an app key', () => {
    expect(parseMeta({ data: { realtime: { ...realtime, key: null } } })).toEqual({ realtime: null });
  });

  it('turns off real time on a missing or broken section', () => {
    expect(parseMeta({ data: {} })).toEqual({ realtime: null });
    expect(parseMeta({ data: { realtime: { ...realtime, port: 'six' } } })).toEqual({ realtime: null });
    expect(parseMeta('nope')).toEqual({ realtime: null });
  });
});
