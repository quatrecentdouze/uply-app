import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useApiClient } from '@/shared/api/apiClientContext';
import { type ApiClient, expectData, expectSuccess } from '@/shared/api/client';
import type { ApiPaths } from '@/shared/api/paths';
import { queryKeys } from '@/shared/api/queryKeys';
import type { AlertChannel } from '@/shared/api/resources';

const listSize = 100;

export function alertChannelsQuery(client: ApiClient, organizationId: string) {
  return queryOptions({
    queryKey: queryKeys.alertChannels.list(organizationId),
    queryFn: async (): Promise<AlertChannel[]> =>
      (
        await expectData(
          client.GET('/v1/organizations/{organization}/alert-channels', { params: { path: { organization: organizationId }, query: { per_page: listSize } } }),
        )
      ).data,
  });
}

export function useAlertChannels(organizationId: string) {
  return useQuery(alertChannelsQuery(useApiClient(), organizationId));
}

export function useMonitorChannels(organizationId: string, monitorId: string | null) {
  const client = useApiClient();
  return useQuery({
    queryKey: queryKeys.alertChannels.ofMonitor(organizationId, monitorId ?? ''),
    enabled: monitorId !== null,
    queryFn: async (): Promise<AlertChannel[]> =>
      (
        await expectData(
          client.GET('/v1/organizations/{organization}/monitors/{monitor}/alert-channels', {
            params: { path: { organization: organizationId, monitor: monitorId ?? '' } },
          }),
        )
      ).data,
  });
}

export interface ChannelLinks {
  monitorId: string;
  channelIds: readonly string[];
}

export function useLinkChannels(organizationId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ monitorId, channelIds }: ChannelLinks): Promise<AlertChannel[]> =>
      (
        await expectData(
          client.PUT('/v1/organizations/{organization}/monitors/{monitor}/alert-channels', {
            params: { path: { organization: organizationId, monitor: monitorId } },
            body: { channel_ids: [...channelIds] },
          }),
        )
      ).data,
    onSuccess: (channels, { monitorId }) => {
      queryClient.setQueryData(queryKeys.alertChannels.ofMonitor(organizationId, monitorId), channels);
    },
  });
}

type JsonBody<Operation> = Operation extends { requestBody?: { content: { 'application/json': infer Body } } } ? Body : never;

export type CreateChannelBody = JsonBody<ApiPaths['/v1/organizations/{organization}/alert-channels']['post']>;

export type UpdateChannelBody = JsonBody<ApiPaths['/v1/organizations/{organization}/alert-channels/{channel}']['patch']>;

function useChannelsChanged(organizationId: string) {
  const queryClient = useQueryClient();
  return async () => {
    await queryClient.invalidateQueries({ queryKey: queryKeys.alertChannels.all(organizationId) });
  };
}

export function useCreateChannel(organizationId: string) {
  const client = useApiClient();
  const changed = useChannelsChanged(organizationId);
  return useMutation({
    mutationFn: async (body: CreateChannelBody): Promise<AlertChannel> =>
      (await expectData(client.POST('/v1/organizations/{organization}/alert-channels', { params: { path: { organization: organizationId } }, body }))).data,
    onSuccess: changed,
  });
}

export interface ChannelUpdate {
  channelId: string;
  body: UpdateChannelBody;
}

export function useUpdateChannel(organizationId: string) {
  const client = useApiClient();
  const changed = useChannelsChanged(organizationId);
  return useMutation({
    mutationFn: async ({ channelId, body }: ChannelUpdate): Promise<AlertChannel> =>
      (
        await expectData(
          client.PATCH('/v1/organizations/{organization}/alert-channels/{channel}', { params: { path: { organization: organizationId, channel: channelId } }, body }),
        )
      ).data,
    onSuccess: changed,
  });
}

export function useDeleteChannel(organizationId: string) {
  const client = useApiClient();
  const changed = useChannelsChanged(organizationId);
  return useMutation({
    mutationFn: async (channelId: string) => {
      await expectSuccess(client.DELETE('/v1/organizations/{organization}/alert-channels/{channel}', { params: { path: { organization: organizationId, channel: channelId } } }));
    },
    onSuccess: changed,
  });
}

export function useTestChannel(organizationId: string) {
  const client = useApiClient();
  return useMutation({
    mutationFn: async (channelId: string) => {
      await expectData(client.POST('/v1/organizations/{organization}/alert-channels/{channel}/test', { params: { path: { organization: organizationId, channel: channelId } } }));
    },
  });
}
