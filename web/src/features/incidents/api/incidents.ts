import { queryOptions, useQuery } from '@tanstack/react-query';
import { useApiClient } from '@/shared/api/apiClientContext';
import { type ApiClient, expectData } from '@/shared/api/client';
import { type IncidentListFilters, queryKeys } from '@/shared/api/queryKeys';
import type { IncidentListResponse } from '@/shared/api/resources';

export function incidentListQuery(client: ApiClient, organizationId: string, filters: IncidentListFilters) {
  return queryOptions({
    queryKey: queryKeys.incidents.list(organizationId, filters),
    queryFn: async (): Promise<IncidentListResponse> =>
      expectData(
        client.GET('/v1/organizations/{organization}/incidents', {
          params: {
            path: { organization: organizationId },
            query: {
              ...(filters.state === undefined ? {} : { state: filters.state }),
              ...(filters.monitor === undefined ? {} : { monitor: filters.monitor }),
              ...(filters.page === undefined ? {} : { page: filters.page }),
              ...(filters.perPage === undefined ? {} : { per_page: filters.perPage }),
            },
          },
        }),
      ),
  });
}

export function useIncidents(organizationId: string, filters: IncidentListFilters) {
  return useQuery(incidentListQuery(useApiClient(), organizationId, filters));
}

const openCountFilters: IncidentListFilters = { state: 'open', perPage: 1 };

export function useOpenIncidentCount(organizationId: string): number | null {
  const query = useQuery({ ...incidentListQuery(useApiClient(), organizationId, openCountFilters), select: (list) => list.meta.total });
  return query.data ?? null;
}
