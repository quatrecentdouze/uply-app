import { queryOptions, useQuery } from '@tanstack/react-query';
import { useApiClient } from '@/shared/api/apiClientContext';
import { type ApiClient, expectData } from '@/shared/api/client';
import { queryKeys } from '@/shared/api/queryKeys';
import type { paths } from '@/shared/api/schema';

export type Meta = paths['/v1/meta']['get']['responses'][200]['content']['application/json']['data'];

export interface Bounds {
  min: number;
  max: number;
}

export type AccountLengths = Meta['organizations']['lengths'];

export interface RealtimeConnection {
  key: string;
  host: string;
  port: number;
  scheme: string;
}

export function realtimeConnection(meta: Meta): RealtimeConnection | null {
  const { key, host, port, scheme } = meta.realtime;
  return key === null || key === '' ? null : { key, host, port, scheme };
}

export function metaQuery(client: ApiClient) {
  return queryOptions({
    queryKey: queryKeys.meta(),
    queryFn: async (): Promise<Meta> => (await expectData(client.GET('/v1/meta'))).data,
    staleTime: Number.POSITIVE_INFINITY,
  });
}

export function useMeta(): Meta | null {
  return useQuery(metaQuery(useApiClient())).data ?? null;
}
