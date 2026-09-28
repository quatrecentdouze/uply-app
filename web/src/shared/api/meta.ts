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

const bounds = z.object({
  min: z.number().int().min(0),
  max: z.number().int().min(1),
});

const organizationsSection = z.object({
  lengths: z.object({
    organization_name: bounds,
    user_name: bounds,
    password: bounds,
  }),
});

const metaBody = z.object({
  data: z.object({
    realtime: z.unknown().optional(),
    organizations: z.unknown().optional(),
  }),
});

export interface RealtimeConnection {
  key: string;
  host: string;
  port: number;
  scheme: 'http' | 'https';
}

export interface Bounds {
  min: number;
  max: number;
}

export interface AccountLengths {
  organizationName: Bounds;
  userName: Bounds;
  password: Bounds;
}

export interface Meta {
  realtime: RealtimeConnection | null;
  lengths: AccountLengths | null;
}

function realtimeOf(section: unknown): RealtimeConnection | null {
  const realtime = realtimeSection.safeParse(section);
  if (!realtime.success || realtime.data.key === null) {
    return null;
  }
  return { ...realtime.data, key: realtime.data.key };
}

function lengthsOf(section: unknown): AccountLengths | null {
  const organizations = organizationsSection.safeParse(section);
  if (!organizations.success) {
    return null;
  }
  const { lengths } = organizations.data;
  return { organizationName: lengths.organization_name, userName: lengths.user_name, password: lengths.password };
}

export function parseMeta(body: unknown): Meta {
  const meta = metaBody.safeParse(body);
  return {
    realtime: realtimeOf(meta.success ? meta.data.data.realtime : undefined),
    lengths: lengthsOf(meta.success ? meta.data.data.organizations : undefined),
  };
}

export function metaQuery(client: ApiClient) {
  return queryOptions({
    queryKey: queryKeys.meta(),
    queryFn: async () => parseMeta(await expectData(client.GET('/v1/meta'))),
    staleTime: Number.POSITIVE_INFINITY,
  });
}
