export interface RetryableQuery {
  data: unknown;
  isError: boolean;
  refetch: () => Promise<unknown>;
}

export type Retry = (() => void) | null;

export function retryOf(query: RetryableQuery): Retry {
  if (!query.isError || query.data !== undefined) {
    return null;
  }
  return () => {
    void query.refetch();
  };
}
