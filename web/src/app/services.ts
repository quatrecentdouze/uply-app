import { QueryClient } from '@tanstack/react-query';
import type { RouterHistory } from '@tanstack/react-router';
import { type AppRouter, createAppRouter } from '@/app/router';
import { type ApiClient, createApiClient } from '@/shared/api/client';
import { createI18n, type I18n } from '@/shared/i18n/i18n';

export interface AppServices {
  queryClient: QueryClient;
  apiClient: ApiClient;
  i18n: I18n;
  router: AppRouter;
}

export interface AppServiceOptions {
  origin: string;
  fetch?: typeof globalThis.fetch;
  readCookies?: () => string;
  history?: RouterHistory;
  retries?: number;
}

const queryDefaults = {
  staleMilliseconds: 30_000,
  retries: 1,
};

export function createAppServices(options: AppServiceOptions): AppServices {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { staleTime: queryDefaults.staleMilliseconds, retry: options.retries ?? queryDefaults.retries },
      mutations: { retry: false },
    },
  });
  const apiClient = createApiClient({
    origin: options.origin,
    ...(options.fetch === undefined ? {} : { fetch: options.fetch }),
    ...(options.readCookies === undefined ? {} : { readCookies: options.readCookies }),
  });
  return {
    queryClient,
    apiClient,
    i18n: createI18n(),
    router: createAppRouter({ queryClient, apiClient }, options.history),
  };
}
