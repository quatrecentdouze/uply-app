import type { Me } from '@/shared/api/resources';

export type Session =
  | { kind: 'guest' }
  | { kind: 'unverified'; me: Me }
  | { kind: 'no_organization'; me: Me }
  | { kind: 'member'; me: Me };

export type RouteAccess = 'public' | 'guest' | 'unverified' | 'verified' | 'member';

export type EntryPath = '/dashboard' | '/login' | '/verify-email' | '/organizations/new';

export interface EntryTarget {
  to: EntryPath;
  redirect: string | null;
}

export function sessionOf(me: Me | null): Session {
  if (me === null) {
    return { kind: 'guest' };
  }
  if (me.email_verified_at === null) {
    return { kind: 'unverified', me };
  }
  return me.memberships.length === 0 ? { kind: 'no_organization', me } : { kind: 'member', me };
}

export function homeOf(session: Session): EntryPath {
  switch (session.kind) {
    case 'guest':
      return '/login';
    case 'unverified':
      return '/verify-email';
    case 'no_organization':
      return '/organizations/new';
    case 'member':
      return '/dashboard';
  }
}

const home = '/dashboard';

function target(to: EntryPath, redirect: string | null = null): EntryTarget {
  return { to, redirect };
}

function signIn(location: string): EntryTarget {
  return target('/login', location === home ? null : location);
}

export function entryRedirect(session: Session, access: RouteAccess, location: string): EntryTarget | null {
  switch (access) {
    case 'public':
      return null;
    case 'guest':
      return session.kind === 'guest' ? null : target(homeOf(session));
    case 'unverified':
      if (session.kind === 'guest') {
        return signIn(location);
      }
      return session.kind === 'unverified' ? null : target(homeOf(session));
    case 'verified':
      if (session.kind === 'guest') {
        return signIn(location);
      }
      return session.kind === 'unverified' ? target('/verify-email') : null;
    case 'member':
      if (session.kind === 'guest') {
        return signIn(location);
      }
      return session.kind === 'member' ? null : target(homeOf(session));
  }
}

export function targetHref(entry: EntryTarget): string {
  return entry.redirect === null ? entry.to : `${entry.to}?${new URLSearchParams({ redirect: entry.redirect }).toString()}`;
}

export function safeRedirect(value: unknown): string | null {
  if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || value.includes('\\')) {
    return null;
  }
  return value;
}
