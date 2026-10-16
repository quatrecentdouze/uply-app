import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useApiClient } from '@/shared/api/apiClientContext';
import { type ApiClient, expectData, expectSuccess } from '@/shared/api/client';
import type { paths } from '@/shared/api/schema';
import { queryKeys } from '@/shared/api/queryKeys';
import type { StatusPage } from '@/shared/api/resources';

type JsonBody<Operation> = Operation extends { requestBody?: { content: { 'application/json': infer Body } } } ? Body : never;

export type CreateStatusPageBody = JsonBody<paths['/v1/organizations/{organization}/status-pages']['post']>;

export type UpdateStatusPageBody = JsonBody<paths['/v1/organizations/{organization}/status-pages/{statusPage}']['patch']>;

const listSize = 100;

export function statusPagesQuery(client: ApiClient, organizationId: string) {
  return queryOptions({
    queryKey: queryKeys.statusPages.list(organizationId),
    queryFn: async (): Promise<StatusPage[]> =>
      (
        await expectData(
          client.GET('/v1/organizations/{organization}/status-pages', { params: { path: { organization: organizationId }, query: { per_page: listSize } } }),
        )
      ).data,
  });
}

export function useStatusPages(organizationId: string) {
  return useQuery(statusPagesQuery(useApiClient(), organizationId));
}

export function useStatusPage(organizationId: string, statusPageId: string) {
  const client = useApiClient();
  return useQuery({
    queryKey: queryKeys.statusPages.detail(organizationId, statusPageId),
    queryFn: async (): Promise<StatusPage> =>
      (
        await expectData(
          client.GET('/v1/organizations/{organization}/status-pages/{statusPage}', { params: { path: { organization: organizationId, statusPage: statusPageId } } }),
        )
      ).data,
  });
}

export function useCreateStatusPage(organizationId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (body: CreateStatusPageBody): Promise<StatusPage> =>
      (await expectData(client.POST('/v1/organizations/{organization}/status-pages', { params: { path: { organization: organizationId } }, body }))).data,
    onSuccess: async (page) => {
      queryClient.setQueryData(queryKeys.statusPages.detail(organizationId, page.id), page);
      await queryClient.invalidateQueries({ queryKey: queryKeys.statusPages.list(organizationId) });
    },
  });
}

export interface StatusPageUpdate {
  statusPageId: string;
  body: UpdateStatusPageBody;
}

export function useUpdateStatusPage(organizationId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ statusPageId, body }: StatusPageUpdate): Promise<StatusPage> =>
      (
        await expectData(
          client.PATCH('/v1/organizations/{organization}/status-pages/{statusPage}', {
            params: { path: { organization: organizationId, statusPage: statusPageId } },
            body,
          }),
        )
      ).data,
    onSuccess: async (page) => {
      queryClient.setQueryData(queryKeys.statusPages.detail(organizationId, page.id), page);
      await queryClient.invalidateQueries({ queryKey: queryKeys.statusPages.list(organizationId) });
    },
  });
}

export function useDeleteStatusPage(organizationId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (statusPageId: string) => {
      await expectSuccess(
        client.DELETE('/v1/organizations/{organization}/status-pages/{statusPage}', { params: { path: { organization: organizationId, statusPage: statusPageId } } }),
      );
      return statusPageId;
    },
    onSuccess: async (statusPageId) => {
      queryClient.removeQueries({ queryKey: queryKeys.statusPages.detail(organizationId, statusPageId) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.statusPages.list(organizationId) });
    },
  });
}
