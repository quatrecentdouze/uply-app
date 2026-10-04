import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useApiClient } from '@/shared/api/apiClientContext';
import { type ApiClient, expectData, expectSuccess } from '@/shared/api/client';
import { ApiError, failureFromResponse } from '@/shared/api/errors';
import { queryKeys } from '@/shared/api/queryKeys';
import type { Me } from '@/shared/api/resources';
import type { paths } from '@/shared/api/schema';

const unauthenticated = 401;

export function meQuery(client: ApiClient) {
  return queryOptions({
    queryKey: queryKeys.me(),
    queryFn: async (): Promise<Me | null> => {
      const result = await client.GET('/v1/me').catch((): never => {
        throw new ApiError({ kind: 'network' });
      });
      if (result.response.status === unauthenticated) {
        return null;
      }
      if (result.data === undefined) {
        throw new ApiError(failureFromResponse(result.response.status, result.error, result.response.headers));
      }
      return result.data.data;
    },
  });
}

export function useMe() {
  return useQuery(meQuery(useApiClient()));
}

type JsonBody<Operation> = Operation extends { requestBody: { content: { 'application/json': infer Body } } } ? Body : never;

export type RegisterBody = JsonBody<paths['/v1/auth/register']['post']>;

export type ResetPasswordBody = JsonBody<paths['/v1/auth/reset-password']['post']>;

export interface Credentials {
  email: string;
  password: string;
}

export interface LoginOutcome {
  twoFactor: boolean;
}

export type TwoFactorAnswer = { code: string } | { recovery_code: string };

function useSessionChange() {
  const queryClient = useQueryClient();
  return async () => {
    queryClient.removeQueries({ predicate: (query) => query.queryKey[0] !== queryKeys.meta()[0] });
    await queryClient.invalidateQueries({ queryKey: queryKeys.me() });
  };
}

export function useLogin() {
  const client = useApiClient();
  const sessionChanged = useSessionChange();
  return useMutation({
    mutationFn: async (credentials: Credentials): Promise<LoginOutcome> => {
      const answer = await expectData(client.POST('/v1/auth/login', { body: credentials }));
      return { twoFactor: answer.two_factor };
    },
    onSuccess: async (outcome) => {
      if (!outcome.twoFactor) {
        await sessionChanged();
      }
    },
  });
}

export function useTwoFactorChallenge() {
  const client = useApiClient();
  const sessionChanged = useSessionChange();
  return useMutation({
    mutationFn: async (answer: TwoFactorAnswer) => {
      await expectSuccess(client.POST('/v1/auth/two-factor-challenge', { body: answer }));
    },
    onSuccess: sessionChanged,
  });
}

export function useRegister() {
  const client = useApiClient();
  const sessionChanged = useSessionChange();
  return useMutation({
    mutationFn: async (body: RegisterBody) => {
      await expectSuccess(client.POST('/v1/auth/register', { body }));
    },
    onSuccess: sessionChanged,
  });
}

export function useLogout() {
  const client = useApiClient();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async () => {
      await expectSuccess(client.POST('/v1/auth/logout'));
    },
    onSettled: () => {
      queryClient.setQueryData(queryKeys.me(), null);
    },
  });
}

export function useForgotPassword() {
  const client = useApiClient();
  return useMutation({
    mutationFn: async (email: string) => {
      await expectSuccess(client.POST('/v1/auth/forgot-password', { body: { email } }));
    },
  });
}

export function useResetPassword() {
  const client = useApiClient();
  return useMutation({
    mutationFn: async (body: ResetPasswordBody) => {
      await expectSuccess(client.POST('/v1/auth/reset-password', { body }));
    },
  });
}

export function useResendVerification() {
  const client = useApiClient();
  return useMutation({
    mutationFn: async () => {
      await expectSuccess(client.POST('/v1/auth/email/verification-notification'));
    },
  });
}
