import { createContext, use } from 'react';
import type { ApiClient } from '@/shared/api/client';

export const ApiClientContext = createContext<ApiClient | null>(null);

export function useApiClient(): ApiClient {
  const client = use(ApiClientContext);
  if (client === null) {
    throw new Error('ApiClientContext is missing');
  }
  return client;
}
