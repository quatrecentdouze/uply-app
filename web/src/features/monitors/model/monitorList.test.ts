import { describe, expect, it } from 'vitest';
import { emptinessOf, listFilters, pauseActionOf } from '@/features/monitors/model/monitorList';
import { monitorFixture } from '@/shared/testing/fixtures';

describe('listFilters', () => {
  it('sends only the filters that narrow the list', () => {
    expect(listFilters('all', '  ', 100)).toEqual({ perPage: 100 });
    expect(listFilters('paused', ' billing ', 100)).toEqual({ state: 'paused', search: 'billing', perPage: 100 });
  });
});

describe('emptinessOf', () => {
  it('tells a first empty list from a filtered one', () => {
    expect(emptinessOf([], { perPage: 100 })).toBe('first');
    expect(emptinessOf([], { state: 'down', perPage: 100 })).toBe('filtered');
    expect(emptinessOf([monitorFixture()], { perPage: 100 })).toBe('none');
  });
});

describe('pauseActionOf', () => {
  it('offers to resume a paused monitor and to pause a running one', () => {
    expect(pauseActionOf({ is_paused: true })).toBe('resume');
    expect(pauseActionOf({ is_paused: false })).toBe('pause');
  });
});
