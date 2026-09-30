import { createContext, use } from 'react';
import type { Organization } from '@/shared/api/resources';
import { browserStore, type KeyValueStore, storageKeys } from '@/shared/storage/storage';

export function rememberOrganization(organizationId: string, store: KeyValueStore = browserStore): void {
  store.write(storageKeys.organization, organizationId);
}

export interface CurrentOrganization {
  organization: Organization;
  organizations: readonly Organization[];
  select: (organizationId: string) => void;
}

export const CurrentOrganizationContext = createContext<CurrentOrganization | null>(null);

export function useCurrentOrganization(): CurrentOrganization {
  const current = use(CurrentOrganizationContext);
  if (current === null) {
    throw new Error('CurrentOrganizationContext is missing');
  }
  return current;
}
