import { describe, expect, it } from 'vitest';
import { abilitiesOf } from '@/shared/api/abilities';
import { metaFixture } from '@/shared/testing/fixtures';

describe('abilitiesOf', () => {
  it('reads the abilities of a role from meta', () => {
    const owner = abilitiesOf(metaFixture(), 'owner');
    const viewer = abilitiesOf(metaFixture(), 'viewer');

    expect(owner.has('organization.delete')).toBe(true);
    expect(viewer.has('monitors.view')).toBe(true);
    expect(viewer.has('monitors.manage')).toBe(false);
  });

  it('grants nothing while meta is missing', () => {
    expect(abilitiesOf(null, 'owner').size).toBe(0);
  });

  it('ignores an ability the web does not know', () => {
    const meta = metaFixture();
    const roles = [{ id: 'member' as const, abilities: ['monitors.manage', 'billing.manage'] }];

    expect([...abilitiesOf({ ...meta, organizations: { ...meta.organizations, roles } }, 'member')]).toEqual(['monitors.manage']);
  });
});
