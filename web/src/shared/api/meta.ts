import { queryOptions } from '@tanstack/react-query';
import { z } from 'zod';
import { type ApiClient, expectData } from '@/shared/api/client';
import { queryKeys } from '@/shared/api/queryKeys';

const maxPort = 65535;

const realtimeSection = z.object({
  key: z.string().min(1).nullable(),
  host: z.string().min(1),
  port: z.number().int().min(1).max(maxPort),
  scheme: z.enum(['http', 'https']),
});

const metaBody = z.object({
  data: z.object({
    realtime: z.unknown().optional(),
  }),
});

export interface RealtimeConnection {
  key: string;
  host: string;
  port: number;
  scheme: 'http' | 'https';
}

export interface Meta {
  realtime: RealtimeConnection | null;
}

export function parseMeta(body: unknown): Meta {
  const meta = metaBody.safeParse(body);
  const realtime = realtimeSection.safeParse(meta.success ? meta.data.data.realtime : undefined);
  if (!realtime.success || realtime.data.key === null) {
    return { realtime: null };
  }
  return { realtime: { ...realtime.data, key: realtime.data.key } };
}

export function metaQuery(client: ApiClient) {
  return queryOptions({
    queryKey: queryKeys.meta(),
    queryFn: async () => parseMeta(await expectData(client.GET('/v1/meta'))),
    staleTime: Number.POSITIVE_INFINITY,
  });
}
