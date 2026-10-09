import { describe, expect, it } from 'vitest';
import { assignableRoles, canChangeMember, defaultInviteRole, inviteSchema, isLastOwner, sortedMembers } from '@/features/organizations/model/members';
import type { Member } from '@/shared/api/resources';

function member(id: string, role: Member['role'], name: string): Member {
  return { id, role, user: { id: `user-${id}`, name, email: `${name.toLowerCase()}@example.test` }, joined_at: null };
}

const texts = { required: 'required', email: 'email', length: () => 'length', mismatch: 'mismatch' };

describe('assignableRoles', () => {
  it('lets an owner grant every role and an admin every role but owner', () => {
    expect(assignableRoles('owner')).toEqual(['owner', 'admin', 'member', 'viewer']);
    expect(assignableRoles('admin')).toEqual(['admin', 'member', 'viewer']);
    expect(assignableRoles('member')).toEqual([]);
  });
});

describe('canChangeMember', () => {
  it('keeps an admin away from the owners', () => {
    expect(canChangeMember('admin', { role: 'owner' })).toBe(false);
    expect(canChangeMember('admin', { role: 'viewer' })).toBe(true);
    expect(canChangeMember('owner', { role: 'owner' })).toBe(true);
    expect(canChangeMember('member', { role: 'viewer' })).toBe(false);
  });
});

describe('isLastOwner', () => {
  it('knows the only owner of the organization', () => {
    const members = [member('1', 'owner', 'Ada'), member('2', 'admin', 'Bob')];

    expect(isLastOwner(members[0] ?? member('x', 'viewer', 'X'), members)).toBe(true);
    expect(isLastOwner(member('3', 'owner', 'Cy'), [...members, member('3', 'owner', 'Cy')])).toBe(false);
    expect(isLastOwner(member('2', 'admin', 'Bob'), members)).toBe(false);
  });
});

describe('sortedMembers', () => {
  it('puts the owners first, then the names in order', () => {
    const sorted = sortedMembers([member('1', 'viewer', 'Ann'), member('2', 'owner', 'Zed'), member('3', 'admin', 'Bob'), member('4', 'owner', 'Amy')]);

    expect(sorted.map((item) => item.user.name)).toEqual(['Amy', 'Zed', 'Bob', 'Ann']);
  });
});

describe('inviteSchema', () => {
  it('needs an email and a role the inviter may grant', () => {
    expect(inviteSchema(texts, 'admin').safeParse({ email: 'new@example.test', role: 'member' }).success).toBe(true);
    expect(inviteSchema(texts, 'admin').safeParse({ email: 'new@example.test', role: 'owner' }).success).toBe(false);
    expect(inviteSchema(texts, 'owner').safeParse({ email: 'nope', role: 'owner' }).success).toBe(false);
  });

  it('invites as member by default', () => {
    expect(defaultInviteRole('admin')).toBe('member');
  });
});
