import { act, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { useCreateMonitor, useDeleteMonitor, useMonitorChecks, useUpdateMonitor } from '@/features/monitors/api/monitors';
import { queryKeys } from '@/shared/api/queryKeys';
import { renderApiHook } from '@/shared/testing/renderApiHook';
import { monitorFixture, organizationId } from '@/shared/testing/fixtures';

const base = `/api/v1/organizations/${organizationId}/monitors`;
const monitor = monitorFixture({ id: 'mon-1' });

function check(checkedAt: string) {
  return { checked_at: checkedAt, outcome: 'up', failure_kind: null, http_status: 200, response_ms: 90, dns_ms: 2, connect_ms: 3, tls_ms: null, ttfb_ms: 80, error_message: null };
}

describe('monitor queries', () => {
  it('pages the checks by cursor', async () => {
    const hook = renderApiHook(() => useMonitorChecks(organizationId, 'mon-1', 2));
    hook.server.respond({ method: 'GET', path: `${base}/mon-1/checks`, body: { data: [check('2026-10-29T12:01:00Z')], links: {}, meta: { path: null, per_page: 2, next_cursor: 'next-1', prev_cursor: null } } });

    await waitFor(() => {
      expect(hook.result.current.data?.pages).toHaveLength(1);
    });
    expect(hook.result.current.hasNextPage).toBe(true);
    hook.server.respond({ method: 'GET', path: `${base}/mon-1/checks`, body: { data: [check('2026-10-29T12:00:00Z')], links: {}, meta: { path: null, per_page: 2, next_cursor: null, prev_cursor: 'x' } } });

    await act(async () => {
      await hook.result.current.fetchNextPage();
    });

    await waitFor(() => {
      expect(hook.result.current.hasNextPage).toBe(false);
    });
    expect(hook.server.requestsTo('GET', `${base}/mon-1/checks`).map((request) => request.search)).toEqual(['?per_page=2', '?per_page=2&cursor=next-1']);
    expect(hook.result.current.data?.pages).toHaveLength(2);
  });
});

describe('monitor mutations', () => {
  it('creates a monitor and refreshes the lists and the overview', async () => {
    const hook = renderApiHook(() => useCreateMonitor(organizationId));
    hook.queryClient.setQueryData(queryKeys.monitors.list(organizationId, {}), { data: [] });
    hook.queryClient.setQueryData(queryKeys.overview.window(organizationId, '24h'), { data: null });
    hook.server.respond({ method: 'POST', path: base, status: 201, body: { data: monitor } });

    await act(async () => {
      await hook.result.current.mutateAsync({ name: 'Public API', url: 'https://api.example.test/health', interval_seconds: 60 });
    });

    expect(hook.server.requestsTo('POST', base)[0]?.body).toEqual({ name: 'Public API', url: 'https://api.example.test/health', interval_seconds: 60 });
    expect(hook.queryClient.getQueryState(queryKeys.monitors.list(organizationId, {}))?.isInvalidated).toBe(true);
    expect(hook.queryClient.getQueryState(queryKeys.overview.window(organizationId, '24h'))?.isInvalidated).toBe(true);
  });

  it('writes the updated monitor into its detail', async () => {
    const hook = renderApiHook(() => useUpdateMonitor(organizationId));
    hook.server.respond({ method: 'PATCH', path: `${base}/mon-1`, body: { data: { ...monitor, is_paused: true } } });

    await act(async () => {
      await hook.result.current.mutateAsync({ monitorId: 'mon-1', body: { is_paused: true } });
    });

    expect(hook.server.requestsTo('PATCH', `${base}/mon-1`)[0]?.body).toEqual({ is_paused: true });
    expect(hook.queryClient.getQueryData(queryKeys.monitors.detail(organizationId, 'mon-1'))).toEqual({ data: { ...monitor, is_paused: true } });
  });

  it('drops the detail of a deleted monitor and refreshes its incidents', async () => {
    const hook = renderApiHook(() => useDeleteMonitor(organizationId));
    hook.queryClient.setQueryData(queryKeys.monitors.detail(organizationId, 'mon-1'), { data: monitor });
    hook.queryClient.setQueryData(queryKeys.incidents.list(organizationId, {}), { data: [] });
    hook.server.respond({ method: 'DELETE', path: `${base}/mon-1`, status: 204 });

    await act(async () => {
      await hook.result.current.mutateAsync('mon-1');
    });

    expect(hook.queryClient.getQueryData(queryKeys.monitors.detail(organizationId, 'mon-1'))).toBeUndefined();
    expect(hook.queryClient.getQueryState(queryKeys.incidents.list(organizationId, {}))?.isInvalidated).toBe(true);
  });
});
