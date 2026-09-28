import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it } from 'vitest';
import { createApiClient } from '@/shared/api/client';
import { queryKeys } from '@/shared/api/queryKeys';
import type { MonitorListResponse, MonitorResponse } from '@/shared/api/resources';
import type { RealtimeConnection } from '@/shared/api/meta';
import { useOrganizationRealtime } from '@/shared/realtime/useOrganizationRealtime';
import { fakeFetch, testOrigin } from '@/shared/testing/fakeFetch';
import { type FakeSocket, fakeSocket } from '@/shared/testing/fakeSocket';
import { monitorFixture, monitorListFixture, organizationId } from '@/shared/testing/fixtures';

const channel = `organization.${organizationId}`;
const connection: RealtimeConnection = { key: 'public-key', host: 'localhost', port: 6001, scheme: 'http' };
const monitor = monitorFixture({ state: 'up', last_response_ms: 120 });
const listKey = queryKeys.monitors.list(organizationId, {});
const detailKey = queryKeys.monitors.detail(organizationId, monitor.id);
const client = createApiClient({ origin: testOrigin, fetch: fakeFetch().fetch, readCookies: () => '' });

function setup(organization: string | null = organizationId) {
  const socket = fakeSocket();
  const createSocket = () => socket;
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  queryClient.setQueryData<MonitorListResponse>(listKey, monitorListFixture([monitor]));
  queryClient.setQueryData<MonitorResponse>(detailKey, { data: monitor });
  queryClient.setQueryData(queryKeys.overview.window(organizationId, '24h'), { data: null });
  queryClient.setQueryData(queryKeys.incidents.list(organizationId, { state: 'open' }), { data: [] });
  function Subscriber() {
    useOrganizationRealtime({ connection, client, organizationId: organization, createSocket });
    return null;
  }
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
  const view = render(<Subscriber />, { wrapper });
  return { socket, queryClient, view };
}

function emit(socket: FakeSocket, event: string, payload: unknown) {
  act(() => {
    socket.emit(channel, `.${event}`, payload);
  });
}

function isInvalidated(queryClient: QueryClient, key: readonly unknown[]): boolean {
  return queryClient.getQueryState(key)?.isInvalidated ?? false;
}

describe('useOrganizationRealtime', () => {
  it('joins the private channel of the organization once', () => {
    const { socket } = setup();

    expect(socket.joined).toEqual([channel]);
  });

  it('writes the state and the last response of monitor.checked into the cache', () => {
    const { socket, queryClient } = setup();

    emit(socket, 'monitor.checked', { monitor_id: monitor.id, checked_at: '2026-10-29T12:00:00Z', outcome: 'down', response_ms: null, state: 'down' });

    expect(queryClient.getQueryData<MonitorListResponse>(listKey)?.data[0]).toMatchObject({ state: 'down', last_response_ms: null, last_checked_at: '2026-10-29T12:00:00Z' });
    expect(queryClient.getQueryData<MonitorResponse>(detailKey)?.data.state).toBe('down');
  });

  it('writes the new state of monitor.state-changed and refreshes the overview', () => {
    const { socket, queryClient } = setup();

    emit(socket, 'monitor.state-changed', { monitor_id: monitor.id, state: 'down', changed_at: '2026-10-29T12:00:00Z' });

    expect(queryClient.getQueryData<MonitorResponse>(detailKey)?.data).toMatchObject({ state: 'down', state_changed_at: '2026-10-29T12:00:00Z' });
    expect(isInvalidated(queryClient, queryKeys.overview.window(organizationId, '24h'))).toBe(true);
    expect(isInvalidated(queryClient, listKey)).toBe(true);
  });

  it('refreshes the monitors and drops a deleted one on monitor.changed', () => {
    const { socket, queryClient } = setup();

    emit(socket, 'monitor.changed', { monitor_id: monitor.id, change: 'deleted' });

    expect(queryClient.getQueryData(detailKey)).toBeUndefined();
    expect(isInvalidated(queryClient, listKey)).toBe(true);
    expect(isInvalidated(queryClient, queryKeys.overview.window(organizationId, '24h'))).toBe(true);
  });

  it('refreshes the incidents on incident.opened', () => {
    const { socket, queryClient } = setup();

    emit(socket, 'incident.opened', { incident_id: 'inc-1', monitor_id: monitor.id, started_at: '2026-10-29T12:00:00Z' });

    expect(isInvalidated(queryClient, queryKeys.incidents.list(organizationId, { state: 'open' }))).toBe(true);
    expect(isInvalidated(queryClient, listKey)).toBe(false);
  });

  it('refreshes the incidents on incident.resolved', () => {
    const { socket, queryClient } = setup();

    emit(socket, 'incident.resolved', { incident_id: 'inc-1', monitor_id: monitor.id, resolved_at: '2026-10-29T12:05:00Z', duration_seconds: 300 });

    expect(isInvalidated(queryClient, queryKeys.incidents.list(organizationId, { state: 'open' }))).toBe(true);
  });

  it('ignores a payload that does not match the contract', () => {
    const { socket, queryClient } = setup();

    emit(socket, 'monitor.checked', { monitor_id: monitor.id, state: 'sideways' });

    expect(queryClient.getQueryData<MonitorResponse>(detailKey)?.data.state).toBe('up');
  });

  it('leaves the channel and closes the socket when unmounted', () => {
    const { socket, view } = setup();

    view.unmount();

    expect(socket.left).toEqual([channel]);
    expect(socket.disconnected).toBe(true);
  });

  it('joins nothing without an organization', () => {
    const { socket } = setup(null);

    expect(socket.joined).toEqual([]);
  });
});
