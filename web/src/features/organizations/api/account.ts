import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useApiClient } from '@/shared/api/apiClientContext';
import { expectData, expectSuccess } from '@/shared/api/client';
import { ApiError } from '@/shared/api/errors';
import { queryKeys } from '@/shared/api/queryKeys';
import type { Me } from '@/shared/api/resources';

export interface ProfileChange {
  name?: string;
  email?: string;
  current_password?: string;
}

export interface PasswordChange {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export interface TwoFactorSetup {
  svg: string;
  url: string;
}

function useMeChanged() {
  const queryClient = useQueryClient();
  return async () => {
    await queryClient.invalidateQueries({ queryKey: queryKeys.me() });
  };
}

export function useUpdateProfile() {
  const client = useApiClient();
  const changed = useMeChanged();
  return useMutation({
    mutationFn: async (body: ProfileChange): Promise<Me> => (await expectData(client.PATCH('/v1/me', { body }))).data,
    onSuccess: changed,
  });
}

export function useUpdatePassword() {
  const client = useApiClient();
  return useMutation({
    mutationFn: async (body: PasswordChange) => {
      await expectSuccess(client.PUT('/v1/me/password', { body }));
    },
  });
}

export function useDeleteAccount() {
  const client = useApiClient();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (password: string) => {
      await expectSuccess(client.DELETE('/v1/me', { body: { password } }));
    },
    onSuccess: () => {
      queryClient.setQueryData(queryKeys.me(), null);
    },
  });
}

export function usePasswordConfirmation() {
  const client = useApiClient();
  return {
    status: async (): Promise<boolean> => (await expectData(client.GET('/v1/auth/user/confirmed-password-status'))).confirmed,
    confirm: async (password: string) => {
      await expectSuccess(client.POST('/v1/auth/user/confirm-password', { body: { password } }));
    },
  };
}

function isSetup(value: unknown): value is TwoFactorSetup {
  return typeof value === 'object' && value !== null && 'svg' in value && 'url' in value && typeof value.svg === 'string' && typeof value.url === 'string';
}

function isCodeList(value: unknown): value is string[] {
  return Array.isArray(value) && value.every((code) => typeof code === 'string');
}

export function useTwoFactor() {
  const client = useApiClient();
  const changed = useMeChanged();
  return {
    enable: async () => {
      await expectSuccess(client.POST('/v1/auth/user/two-factor-authentication', { body: {} }));
    },
    setup: async (): Promise<TwoFactorSetup> => {
      const answer = await expectData(client.GET('/v1/auth/user/two-factor-qr-code'));
      if (!isSetup(answer)) {
        throw new ApiError({ kind: 'server', status: 200 });
      }
      return answer;
    },
    secretKey: async (): Promise<string> => {
      const answer = await expectData(client.GET('/v1/auth/user/two-factor-secret-key'));
      if (typeof answer.secretKey !== 'string') {
        throw new ApiError({ kind: 'server', status: 200 });
      }
      return answer.secretKey;
    },
    confirm: async (code: string) => {
      await expectSuccess(client.POST('/v1/auth/user/confirmed-two-factor-authentication', { body: { code } }));
      await changed();
    },
    recoveryCodes: async (): Promise<string[]> => {
      const answer = await expectData(client.GET('/v1/auth/user/two-factor-recovery-codes'));
      if (!isCodeList(answer)) {
        throw new ApiError({ kind: 'server', status: 200 });
      }
      return answer;
    },
    regenerate: async () => {
      await expectSuccess(client.POST('/v1/auth/user/two-factor-recovery-codes'));
    },
    disable: async () => {
      await expectSuccess(client.DELETE('/v1/auth/user/two-factor-authentication'));
      await changed();
    },
  };
}
