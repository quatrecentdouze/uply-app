import { infiniteQueryOptions, queryOptions, useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useApiClient } from '@/shared/api/apiClientContext';
import { type ApiClient, expectData, expectSuccess } from '@/shared/api/client';
import type { paths } from '@/shared/api/schema';
import { type MonitorListFilters, queryKeys, type Window } from '@/shared/api/queryKeys';
import type { CheckListResponse, Monitor, MonitorListResponse, MonitorResponse, MonitorStats, OverviewResponse } from '@/shared/api/resources';

type JsonBody<Operation> = Operation extends { requestBody?: { content: { 'application/json': infer Body } } } ? Body : never;

export type CreateMonitorBody = JsonBody<paths['/v1/organizations/{organization}/monitors']['post']>;

export type UpdateMonitorBody = JsonBody<paths['/v1/organizations/{organization}/monitors/{monitor}']['patch']>;

export function monitorListQuery(client: ApiClient, organizationId: string, filters: MonitorListFilters) {
  return queryOptions({
    queryKey: queryKeys.monitors.list(organizationId, filters),
    queryFn: async (): Promise<MonitorListResponse> =>
      expectData(
        client.GET('/v1/organizations/{organization}/monitors', {
          params: {
            path: { organization: organizationId },
            query: {
              ...(filters.state === undefined ? {} : { state: filters.state }),
              ...(filters.search === undefined ? {} : { search: filters.search }),
              ...(filters.page === undefined ? {} : { page: filters.page }),
              ...(filters.perPage === undefined ? {} : { per_page: filters.perPage }),
            },
          },
        }),
      ),
  });
}

export function useMonitorList(organizationId: string, filters: MonitorListFilters) {
  return useQuery(monitorListQuery(useApiClient(), organizationId, filters));
}

export function overviewQuery(client: ApiClient, organizationId: string, window: Window) {
  return queryOptions({
    queryKey: queryKeys.overview.window(organizationId, window),
    queryFn: async (): Promise<OverviewResponse> =>
      expectData(client.GET('/v1/organizations/{organization}/overview', { params: { path: { organization: organizationId }, query: { window } } })),
  });
}

export function useOverview(organizationId: string, window: Window) {
  return useQuery(overviewQuery(useApiClient(), organizationId, window));
}

export function monitorQuery(client: ApiClient, organizationId: string, monitorId: string) {
  return queryOptions({
    queryKey: queryKeys.monitors.detail(organizationId, monitorId),
    queryFn: async (): Promise<MonitorResponse> =>
      expectData(client.GET('/v1/organizations/{organization}/monitors/{monitor}', { params: { path: { organization: organizationId, monitor: monitorId } } })),
  });
}

export function useMonitor(organizationId: string, monitorId: string) {
  return useQuery(monitorQuery(useApiClient(), organizationId, monitorId));
}

export function useMonitorStats(organizationId: string, monitorId: string, window: Window) {
  const client = useApiClient();
  return useQuery({
    queryKey: queryKeys.monitors.statsWindow(organizationId, monitorId, window),
    queryFn: async (): Promise<MonitorStats> =>
      (
        await expectData(
          client.GET('/v1/organizations/{organization}/monitors/{monitor}/stats', {
            params: { path: { organization: organizationId, monitor: monitorId }, query: { window } },
          }),
        )
      ).data,
  });
}

const firstCheckPage: string | null = null;

export function monitorChecksQuery(client: ApiClient, organizationId: string, monitorId: string, perPage: number) {
  return infiniteQueryOptions({
    queryKey: queryKeys.monitors.checks(organizationId, monitorId),
    initialPageParam: firstCheckPage,
    queryFn: async ({ pageParam }): Promise<CheckListResponse> =>
      expectData(
        client.GET('/v1/organizations/{organization}/monitors/{monitor}/checks', {
          params: {
            path: { organization: organizationId, monitor: monitorId },
            query: { per_page: perPage, ...(pageParam === null ? {} : { cursor: pageParam }) },
          },
        }),
      ),
    getNextPageParam: (page) => page.meta.next_cursor,
  });
}

export function useMonitorChecks(organizationId: string, monitorId: string, perPage: number) {
  return useInfiniteQuery(monitorChecksQuery(useApiClient(), organizationId, monitorId, perPage));
}

function useMonitorsChanged(organizationId: string) {
  const queryClient = useQueryClient();
  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: queryKeys.monitors.all(organizationId) }),
      queryClient.invalidateQueries({ queryKey: queryKeys.overview.all(organizationId) }),
    ]);
  };
}

async function patchMonitor(client: ApiClient, organizationId: string, monitorId: string, body: UpdateMonitorBody): Promise<Monitor> {
  return (
    await expectData(client.PATCH('/v1/organizations/{organization}/monitors/{monitor}', { params: { path: { organization: organizationId, monitor: monitorId } }, body }))
  ).data;
}

function useMonitorSaved(organizationId: string) {
  const queryClient = useQueryClient();
  const changed = useMonitorsChanged(organizationId);
  return async (monitor: Monitor): Promise<Monitor> => {
    queryClient.setQueryData<MonitorResponse>(queryKeys.monitors.detail(organizationId, monitor.id), { data: monitor });
    await changed();
    return monitor;
  };
}

export interface MonitorWriter {
  create: (body: CreateMonitorBody) => Promise<Monitor>;
  update: (monitorId: string, body: UpdateMonitorBody) => Promise<Monitor>;
}

export function useSaveMonitor(organizationId: string): MonitorWriter {
  const client = useApiClient();
  const saved = useMonitorSaved(organizationId);
  return {
    create: async (body) => saved((await expectData(client.POST('/v1/organizations/{organization}/monitors', { params: { path: { organization: organizationId } }, body }))).data),
    update: async (monitorId, body) => saved(await patchMonitor(client, organizationId, monitorId, body)),
  };
}

export interface MonitorPause {
  monitorId: string;
  paused: boolean;
}

export function usePauseMonitor(organizationId: string) {
  const client = useApiClient();
  const saved = useMonitorSaved(organizationId);
  return useMutation({
    mutationFn: async ({ monitorId, paused }: MonitorPause): Promise<Monitor> => saved(await patchMonitor(client, organizationId, monitorId, { is_paused: paused })),
  });
}

export function useDeleteMonitor(organizationId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();
  const changed = useMonitorsChanged(organizationId);
  return useMutation({
    mutationFn: async (monitorId: string) => {
      await expectSuccess(client.DELETE('/v1/organizations/{organization}/monitors/{monitor}', { params: { path: { organization: organizationId, monitor: monitorId } } }));
      return monitorId;
    },
    onSuccess: async (monitorId) => {
      queryClient.removeQueries({ queryKey: queryKeys.monitors.detail(organizationId, monitorId) });
      await Promise.all([changed(), queryClient.invalidateQueries({ queryKey: queryKeys.incidents.all(organizationId) })]);
    },
  });
}
