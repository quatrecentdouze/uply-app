import { describe, expect, it } from 'vitest';
import { entryRedirect, homeOf, type RouteAccess, safeRedirect, sessionOf, targetHref } from '@/app/model/entryFlow';
import { meFixture } from '@/shared/testing/fixtures';

const guest = sessionOf(null);
const unverified = sessionOf(meFixture({ email_verified_at: null }));
const lonely = sessionOf(meFixture({ memberships: [] }));
const member = sessionOf(meFixture());

describe('sessionOf', () => {
  it('reads the four steps of the entry flow from GET me', () => {
    expect([guest, unverified, lonely, member].map((session) => session.kind)).toEqual(['guest', 'unverified', 'no_organization', 'member']);
  });
});

describe('homeOf', () => {
  it('sends each session to its first screen', () => {
    expect([guest, unverified, lonely, member].map(homeOf)).toEqual(['/login', '/verify-email', '/organizations/new', '/']);
  });
});

describe('entryRedirect', () => {
  const cases: [string, RouteAccess, ReturnType<typeof sessionOf>, string | null][] = [
    ['a guest on a private page goes to sign in and comes back', 'member', guest, '/login?redirect=%2Fmonitors'],
    ['an unverified user on a private page verifies first', 'member', unverified, '/verify-email'],
    ['a user without organization creates one', 'member', lonely, '/organizations/new'],
    ['a member stays', 'member', member, null],
    ['a member on a guest page goes home', 'guest', member, '/'],
    ['an unverified user on a guest page verifies', 'guest', unverified, '/verify-email'],
    ['a guest stays on a guest page', 'guest', guest, null],
    ['a verified user leaves the verify page', 'unverified', member, '/'],
    ['a guest cannot verify', 'unverified', guest, '/login?redirect=%2Fmonitors'],
    ['the create page needs a verified user', 'verified', unverified, '/verify-email'],
    ['the create page is open to a member from the switcher', 'verified', member, null],
    ['a public page lets anyone in', 'public', guest, null],
  ];

  it.each(cases)('%s', (_, access, session, expected) => {
    const entry = entryRedirect(session, access, '/monitors');
    expect(entry === null ? null : targetHref(entry)).toBe(expected);
  });

  it('does not ask to come back to the dashboard', () => {
    expect(entryRedirect(guest, 'member', '/')).toEqual({ to: '/login', redirect: null });
  });
});

describe('safeRedirect', () => {
  it('keeps internal paths only', () => {
    expect(safeRedirect('/invitations/abc?x=1')).toBe('/invitations/abc?x=1');
    expect(safeRedirect('https://evil.test')).toBeNull();
    expect(safeRedirect('//evil.test')).toBeNull();
    expect(safeRedirect('/\\evil.test')).toBeNull();
    expect(safeRedirect(42)).toBeNull();
  });
});
