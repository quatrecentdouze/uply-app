import { queryOptions, useQuery } from '@tanstack/react-query';
import { useApiClient } from '@/shared/api/apiClientContext';
import { type ApiClient, expectData } from '@/shared/api/client';
import { type MonitorListFilters, queryKeys, type Window } from '@/shared/api/queryKeys';
import type { MonitorListResponse, OverviewResponse } from '@/shared/api/resources';

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
