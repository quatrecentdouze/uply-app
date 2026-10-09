import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useApiClient } from '@/shared/api/apiClientContext';
import { type ApiClient, expectData, expectSuccess } from '@/shared/api/client';
import { ApiError, failureFromResponse } from '@/shared/api/errors';
import { queryKeys } from '@/shared/api/queryKeys';
import type { Invitation, Member, Organization, PublicInvitation, Role } from '@/shared/api/resources';

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

export function useRenameOrganization(organizationId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (name: string): Promise<Organization> =>
      (await expectData(client.PATCH('/v1/organizations/{organization}', { params: { path: { organization: organizationId } }, body: { name } }))).data,
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.organizations() }),
        queryClient.invalidateQueries({ queryKey: queryKeys.me() }),
      ]);
    },
  });
}

export function useDeleteOrganization(organizationId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (password: string) => {
      await expectSuccess(client.DELETE('/v1/organizations/{organization}', { params: { path: { organization: organizationId } }, body: { password } }));
    },
    onSuccess: async () => {
      queryClient.removeQueries({ queryKey: queryKeys.organization(organizationId) });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.organizations() }),
        queryClient.invalidateQueries({ queryKey: queryKeys.me() }),
      ]);
    },
  });
}

export function useMembers(organizationId: string) {
  const client = useApiClient();
  return useQuery({
    queryKey: queryKeys.members(organizationId),
    queryFn: async (): Promise<Member[]> =>
      (await expectData(client.GET('/v1/organizations/{organization}/members', { params: { path: { organization: organizationId }, query: { per_page: listSize } } }))).data,
  });
}

export interface RoleChange {
  membershipId: string;
  role: Role;
}

export function useChangeRole(organizationId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ membershipId, role }: RoleChange): Promise<Member> =>
      (
        await expectData(
          client.PATCH('/v1/organizations/{organization}/members/{membership}', { params: { path: { organization: organizationId, membership: membershipId } }, body: { role } }),
        )
      ).data,
    onSettled: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.members(organizationId) }),
        queryClient.invalidateQueries({ queryKey: queryKeys.organizations() }),
        queryClient.invalidateQueries({ queryKey: queryKeys.me() }),
      ]);
    },
  });
}

export function useRemoveMember(organizationId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (membershipId: string) => {
      await expectSuccess(
        client.DELETE('/v1/organizations/{organization}/members/{membership}', { params: { path: { organization: organizationId, membership: membershipId } } }),
      );
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.members(organizationId) });
    },
  });
}

export function useLeaveOrganization(organizationId: string) {
  const client = useApiClient();
  const membershipChanged = useMembershipChange();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (membershipId: string) => {
      await expectSuccess(
        client.DELETE('/v1/organizations/{organization}/members/{membership}', { params: { path: { organization: organizationId, membership: membershipId } } }),
      );
    },
    onSuccess: async () => {
      queryClient.removeQueries({ queryKey: queryKeys.organization(organizationId) });
      await membershipChanged();
    },
  });
}

export function useInvitations(organizationId: string, enabled: boolean) {
  const client = useApiClient();
  return useQuery({
    queryKey: queryKeys.invitations(organizationId),
    enabled,
    queryFn: async (): Promise<Invitation[]> =>
      (await expectData(client.GET('/v1/organizations/{organization}/invitations', { params: { path: { organization: organizationId }, query: { per_page: listSize } } }))).data,
  });
}

export function useInvite(organizationId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (body: { email: string; role: Role }): Promise<Invitation> =>
      (await expectData(client.POST('/v1/organizations/{organization}/invitations', { params: { path: { organization: organizationId } }, body }))).data,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.invitations(organizationId) });
    },
  });
}

export function useRevokeInvitation(organizationId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (invitationId: string) => {
      await expectSuccess(
        client.DELETE('/v1/organizations/{organization}/invitations/{invitation}', { params: { path: { organization: organizationId, invitation: invitationId } } }),
      );
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.invitations(organizationId) });
    },
  });
}
