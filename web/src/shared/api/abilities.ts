import type { Meta } from '@/shared/api/meta';
import type { Role } from '@/shared/api/resources';

export const abilities = [
  'monitors.view',
  'monitors.manage',
  'incidents.view',
  'status-pages.view',
  'status-pages.manage',
  'alert-channels.view',
  'alert-channels.manage',
  'members.manage',
  'organization.manage',
  'organization.delete',
] as const;

export type Ability = (typeof abilities)[number];

function isAbility(value: string): value is Ability {
  return abilities.some((ability) => ability === value);
}

export function abilitiesOf(meta: Meta | null, role: Role): ReadonlySet<Ability> {
  const granted = meta?.organizations.roles.find((candidate) => candidate.id === role)?.abilities ?? [];
  return new Set(granted.filter(isAbility));
}
