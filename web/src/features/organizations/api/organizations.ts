import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useApiClient } from '@/shared/api/apiClientContext';
import { type ApiClient, expectData } from '@/shared/api/client';
import { ApiError, failureFromResponse } from '@/shared/api/errors';
import { queryKeys } from '@/shared/api/queryKeys';
import type { Organization, PublicInvitation } from '@/shared/api/resources';

const listSize = 100;

export function organizationsQuery(client: ApiClient) {
  return queryOptions({
    queryKey: queryKeys.organizations(),
    queryFn: async (): Promise<Organization[]> =>
      (await expectData(client.GET('/v1/organizations', { params: { query: { per_page: listSize } } }))).data,
  });
}

export function useOrganizations() {
  return useQuery(organizationsQuery(useApiClient()));
}

function useMembershipChange() {
  const queryClient = useQueryClient();
  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: queryKeys.me() }),
      queryClient.invalidateQueries({ queryKey: queryKeys.organizations() }),
    ]);
  };
}

export function useCreateOrganization() {
  const client = useApiClient();
  const membershipChanged = useMembershipChange();
  return useMutation({
    mutationFn: async (name: string): Promise<Organization> => (await expectData(client.POST('/v1/organizations', { body: { name } }))).data,
    onSuccess: membershipChanged,
  });
}

export type InvitationLookup = { kind: 'pending'; invitation: PublicInvitation } | { kind: 'expired' } | { kind: 'missing' };

const gone = 410;
const missing = 404;

export function invitationQuery(client: ApiClient, token: string) {
  return queryOptions({
    queryKey: queryKeys.invitation(token),
    queryFn: async (): Promise<InvitationLookup> => {
      const result = await client.GET('/v1/invitations/{token}', { params: { path: { token } } }).catch((): never => {
        throw new ApiError({ kind: 'network' });
      });
      if (result.response.status === gone) {
        return { kind: 'expired' };
      }
      if (result.response.status === missing) {
        return { kind: 'missing' };
      }
      if (result.data === undefined) {
        throw new ApiError(failureFromResponse(result.response.status, result.error, result.response.headers));
      }
      return { kind: 'pending', invitation: result.data.data };
    },
  });
}

export function useInvitation(token: string) {
  return useQuery(invitationQuery(useApiClient(), token));
}

export function useAcceptInvitation(token: string) {
  const client = useApiClient();
  const membershipChanged = useMembershipChange();
  return useMutation({
    mutationFn: async (): Promise<Organization> =>
      (await expectData(client.POST('/v1/invitations/{token}/accept', { params: { path: { token } } }))).data,
    onSuccess: membershipChanged,
  });
}
