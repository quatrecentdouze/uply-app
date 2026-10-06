import { useMemo } from 'react';
import { useCurrentOrganization } from '@/features/organizations/hooks/currentOrganization';
import { type Ability, abilitiesOf } from '@/shared/api/abilities';
import { useMeta } from '@/shared/api/meta';
import type { Organization } from '@/shared/api/resources';

export interface Workspace {
  organization: Organization;
  organizationId: string;
  can: (ability: Ability) => boolean;
}

export function useWorkspace(): Workspace {
  const { organization } = useCurrentOrganization();
  const meta = useMeta();
  return useMemo(() => {
    const granted = abilitiesOf(meta, organization.role);
    return { organization, organizationId: organization.id, can: (ability) => granted.has(ability) };
  }, [meta, organization]);
}
