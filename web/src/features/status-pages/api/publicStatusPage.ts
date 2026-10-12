import { queryOptions, useQuery } from '@tanstack/react-query';
import { useApiClient } from '@/shared/api/apiClientContext';
import { type ApiClient, expectData } from '@/shared/api/client';
import { queryKeys } from '@/shared/api/queryKeys';
import type { PublicStatusPage } from '@/shared/api/resources';

const millisecondsPerSecond = 1000;

export function publicStatusPageQuery(client: ApiClient, slug: string) {
  return queryOptions({
    queryKey: queryKeys.publicStatusPage(slug),
    queryFn: async (): Promise<PublicStatusPage> => (await expectData(client.GET('/v1/public/status-pages/{slug}', { params: { path: { slug } } }))).data,
    retry: false,
  });
}

export function usePublicStatusPage(slug: string, refreshSeconds: number | null) {
  return useQuery({
    ...publicStatusPageQuery(useApiClient(), slug),
    refetchInterval: refreshSeconds === null ? false : refreshSeconds * millisecondsPerSecond,
    refetchIntervalInBackground: false,
  });
}
