import { describe, expect, it, vi } from 'vitest';
import { retryOf } from '@/shared/api/retry';

describe('retryOf', () => {
  it('offers a retry only for a query that failed without data', () => {
    const refetch = vi.fn(() => Promise.resolve());

    expect(retryOf({ data: undefined, isError: false, refetch })).toBeNull();
    expect(retryOf({ data: { id: 'kept' }, isError: true, refetch })).toBeNull();

    retryOf({ data: undefined, isError: true, refetch })?.();
    expect(refetch).toHaveBeenCalledOnce();
  });
});
