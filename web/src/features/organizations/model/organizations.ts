import { z } from 'zod';
import type { Organization, PublicInvitation, Role } from '@/shared/api/resources';
import type { Bounds } from '@/shared/api/meta';
import type { TextKey } from '@/shared/i18n/textKey';
import type { BaseValidationTexts } from '@/shared/i18n/useValidationTexts';

export function chooseOrganization(storedId: string | null, organizations: readonly Organization[]): Organization | null {
  return organizations.find((organization) => organization.id === storedId) ?? organizations[0] ?? null;
}

export const roleTextKeys: Record<Role, TextKey<'organizations'>> = {
  owner: 'organizations:roles.owner',
  admin: 'organizations:roles.admin',
  member: 'organizations:roles.member',
  viewer: 'organizations:roles.viewer',
};

export function organizationSchema(texts: BaseValidationTexts, bounds: Bounds | null) {
  const name = z.string().trim().min(1, texts.required);
  return z.object({
    name: bounds === null ? name : name.min(bounds.min, texts.length(bounds)).max(bounds.max, texts.length(bounds)),
  });
}

export type OrganizationValues = z.infer<ReturnType<typeof organizationSchema>>;

export type InvitationViewer = { kind: 'guest' } | { kind: 'unverified'; email: string } | { kind: 'verified'; email: string };

export type InvitationStep = 'sign_in' | 'verify_email' | 'switch_account' | 'accept';

export function invitationStep(viewer: InvitationViewer, invitation: PublicInvitation): InvitationStep {
  if (viewer.kind === 'guest') {
    return 'sign_in';
  }
  if (viewer.email.toLowerCase() !== invitation.email.toLowerCase()) {
    return 'switch_account';
  }
  return viewer.kind === 'unverified' ? 'verify_email' : 'accept';
}
