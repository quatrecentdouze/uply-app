import { z } from 'zod';
import type { Member, Role } from '@/shared/api/resources';
import type { BaseValidationTexts } from '@/shared/i18n/useValidationTexts';

export const roles: readonly Role[] = ['owner', 'admin', 'member', 'viewer'];

export function assignableRoles(actor: Role): Role[] {
  if (actor === 'owner') {
    return [...roles];
  }
  return actor === 'admin' ? roles.filter((role) => role !== 'owner') : [];
}

export function canChangeMember(actor: Role, target: Pick<Member, 'role'>): boolean {
  return assignableRoles(actor).length > 0 && (actor === 'owner' || target.role !== 'owner');
}

export function ownerCount(members: readonly Pick<Member, 'role'>[]): number {
  return members.filter((member) => member.role === 'owner').length;
}

export function isLastOwner(member: Pick<Member, 'role'>, members: readonly Pick<Member, 'role'>[]): boolean {
  return member.role === 'owner' && ownerCount(members) <= 1;
}

export function sortedMembers(members: readonly Member[]): Member[] {
  return [...members].sort((left, right) => roles.indexOf(left.role) - roles.indexOf(right.role) || left.user.name.localeCompare(right.user.name));
}

export function inviteSchema(texts: BaseValidationTexts, actor: Role) {
  const allowed = assignableRoles(actor);
  return z.object({
    email: z.string().trim().min(1, texts.required).pipe(z.email(texts.email)),
    role: z.enum(['owner', 'admin', 'member', 'viewer']).refine((role) => allowed.includes(role), texts.required),
  });
}

export type InviteValues = z.infer<ReturnType<typeof inviteSchema>>;

export function defaultInviteRole(actor: Role): Role {
  return assignableRoles(actor).includes('member') ? 'member' : 'viewer';
}
