import { QueryClientProvider } from '@tanstack/react-query';
import { renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { ApiClientContext } from '@/shared/api/apiClientContext';
import { createApiClient } from '@/shared/api/client';
import { type FakeFetch, fakeFetch, testOrigin } from '@/shared/testing/fakeFetch';
import { testQueryClient } from '@/shared/testing/renderPage';

export function renderApiHook<Result>(hook: () => Result, server: FakeFetch = fakeFetch([{ method: 'GET', path: '/sanctum/csrf-cookie', status: 204 }])) {
  const queryClient = testQueryClient();
  const client = createApiClient({ origin: testOrigin, fetch: server.fetch, readCookies: () => 'XSRF-TOKEN=test-token' });
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <ApiClientContext value={client}>{children}</ApiClientContext>
    </QueryClientProvider>
  );
  return { ...renderHook(hook, { wrapper }), server, queryClient };
}
