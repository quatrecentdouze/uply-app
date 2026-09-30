import { type ReactNode, useMemo, useState } from 'react';
import { CurrentOrganizationContext, type CurrentOrganization } from '@/features/organizations/hooks/currentOrganization';
import { chooseOrganization } from '@/features/organizations/model/organizations';
import type { Organization } from '@/shared/api/resources';
import { browserStore, type KeyValueStore, storageKeys } from '@/shared/storage/storage';

interface CurrentOrganizationProviderProps {
  organizations: readonly Organization[];
  store?: KeyValueStore;
  fallback: ReactNode;
  children: ReactNode;
}

export function CurrentOrganizationProvider({ organizations, store = browserStore, fallback, children }: CurrentOrganizationProviderProps) {
  const [storedId, setStoredId] = useState(() => store.read(storageKeys.organization));
  const organization = chooseOrganization(storedId, organizations);

  const current = useMemo<CurrentOrganization | null>(() => {
    if (organization === null) {
      return null;
    }
    return {
      organization,
      organizations,
      select: (organizationId) => {
        store.write(storageKeys.organization, organizationId);
        setStoredId(organizationId);
      },
    };
  }, [organization, organizations, store]);

  if (current === null) {
    return fallback;
  }
  return <CurrentOrganizationContext value={current}>{children}</CurrentOrganizationContext>;
}
