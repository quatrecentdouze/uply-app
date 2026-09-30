import { describe, expect, it } from 'vitest';
import { chooseOrganization, invitationStep, organizationSchema } from '@/features/organizations/model/organizations';
import type { Organization, PublicInvitation } from '@/shared/api/resources';

function organization(id: string): Organization {
  return { id, name: id, slug: id, role: 'owner', created_at: null };
}

const invitation: PublicInvitation = {
  organization: { name: 'Acme' },
  role: 'member',
  invited_by: { name: 'Ada' },
  email: 'Invitee@Example.test',
  expires_at: '2026-11-05T00:00:00Z',
};

describe('chooseOrganization', () => {
  const organizations = [organization('a'), organization('b')];

  it('keeps the stored organization when it is still a membership', () => {
    expect(chooseOrganization('b', organizations)?.id).toBe('b');
  });

  it('falls back to the first organization on a stale or missing id', () => {
    expect(chooseOrganization('gone', organizations)?.id).toBe('a');
    expect(chooseOrganization(null, organizations)?.id).toBe('a');
  });

  it('answers null without any organization', () => {
    expect(chooseOrganization('a', [])).toBeNull();
  });
});

describe('invitationStep', () => {
  it('asks a guest to sign in', () => {
    expect(invitationStep({ kind: 'guest' }, invitation)).toBe('sign_in');
  });

  it('compares the emails without case', () => {
    expect(invitationStep({ kind: 'verified', email: 'invitee@example.test' }, invitation)).toBe('accept');
    expect(invitationStep({ kind: 'verified', email: 'other@example.test' }, invitation)).toBe('switch_account');
  });

  it('asks the invited user to verify the email first', () => {
    expect(invitationStep({ kind: 'unverified', email: 'invitee@example.test' }, invitation)).toBe('verify_email');
  });
});

describe('organizationSchema', () => {
  const texts = { required: 'required', email: 'email', length: () => 'length', mismatch: 'mismatch' };

  it('checks the name bounds from meta after trimming', () => {
    expect(organizationSchema(texts, { min: 2, max: 80 }).safeParse({ name: ' A ' }).success).toBe(false);
    expect(organizationSchema(texts, { min: 2, max: 80 }).safeParse({ name: 'Acme' }).success).toBe(true);
    expect(organizationSchema(texts, null).safeParse({ name: '' }).success).toBe(false);
  });
});
