import { describe, expect, it } from 'vitest';
import { parseMeta } from '@/shared/api/meta';

const realtime = { key: 'public-key', host: 'localhost', port: 6001, scheme: 'http' };
const organizations = {
  roles: [],
  lengths: {
    organization_name: { min: 2, max: 80 },
    slug: { min: 3, max: 48 },
    user_name: { min: 1, max: 80 },
    password: { min: 12, max: 255 },
  },
};

describe('parseMeta', () => {
  it('reads the realtime connection', () => {
    expect(parseMeta({ data: { api: {}, realtime } }).realtime).toEqual(realtime);
  });

  it('turns off real time without an app key', () => {
    expect(parseMeta({ data: { realtime: { ...realtime, key: null } } }).realtime).toBeNull();
  });

  it('turns off real time on a missing or broken section', () => {
    expect(parseMeta({ data: {} }).realtime).toBeNull();
    expect(parseMeta({ data: { realtime: { ...realtime, port: 'six' } } }).realtime).toBeNull();
    expect(parseMeta('nope')).toEqual({ realtime: null, lengths: null });
  });

  it('reads the account lengths the forms check', () => {
    expect(parseMeta({ data: { organizations } }).lengths).toEqual({
      organizationName: { min: 2, max: 80 },
      userName: { min: 1, max: 80 },
      password: { min: 12, max: 255 },
    });
  });
});
