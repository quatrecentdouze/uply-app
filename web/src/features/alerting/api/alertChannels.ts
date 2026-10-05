import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useApiClient } from '@/shared/api/apiClientContext';
import { type ApiClient, expectData } from '@/shared/api/client';
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
