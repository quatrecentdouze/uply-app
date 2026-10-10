import type { PageId } from '@/app/model/pages';
import type { TextKey } from '@/shared/i18n/textKey';

export type NavigationPath = '/' | '/monitors' | '/incidents' | '/status-pages' | '/alert-channels' | '/settings';

export type NavigationAction = 'help' | 'logout';

export type NavigationItem =
  | { kind: 'link'; page: PageId; to: NavigationPath; badge: 'open_incidents' | null }
  | { kind: 'action'; action: NavigationAction };

export interface NavigationGroup {
  id: 'monitoring' | 'alerting' | 'general';
  label: TextKey<'common'>;
  items: readonly NavigationItem[];
}

export const navigationGroups: readonly NavigationGroup[] = [
  {
    id: 'monitoring',
    label: 'common:shell.groups.monitoring',
    items: [
      { kind: 'link', page: 'dashboard', to: '/', badge: null },
      { kind: 'link', page: 'monitors', to: '/monitors', badge: null },
      { kind: 'link', page: 'incidents', to: '/incidents', badge: 'open_incidents' },
      { kind: 'link', page: 'status_pages', to: '/status-pages', badge: null },
    ],
  },
  {
    id: 'alerting',
    label: 'common:shell.groups.alerting',
    items: [{ kind: 'link', page: 'alert_channels', to: '/alert-channels', badge: null }],
  },
  {
    id: 'general',
    label: 'common:shell.groups.general',
    items: [
      { kind: 'link', page: 'settings', to: '/settings', badge: null },
      { kind: 'action', action: 'help' },
      { kind: 'action', action: 'logout' },
    ],
  },
];

export interface SettingsSection {
  to: '/settings/organization' | '/settings/members' | '/settings/profile' | '/settings/security';
  label: TextKey<'organizations'>;
}

export const settingsSections: readonly SettingsSection[] = [
  { to: '/settings/organization', label: 'organizations:settings.tabs.organization' },
  { to: '/settings/members', label: 'organizations:settings.tabs.members' },
  { to: '/settings/profile', label: 'organizations:settings.tabs.profile' },
  { to: '/settings/security', label: 'organizations:settings.tabs.security' },
];

export const navigationActionLabels: Record<NavigationAction, TextKey<'common'>> = {
  help: 'common:pages.help',
  logout: 'common:shell.logout',
};

export function navigationLinks(): Extract<NavigationItem, { kind: 'link' }>[] {
  return navigationGroups.flatMap((group) => group.items.filter((item): item is Extract<NavigationItem, { kind: 'link' }> => item.kind === 'link'));
}

const badgeLimit = 99;

export function badgeText(count: number | null): string | null {
  if (count === null || count <= 0) {
    return null;
  }
  return count > badgeLimit ? `${String(badgeLimit)}+` : String(count);
}

export function initialsOf(name: string, email: string): string {
  const words = name.trim().split(/\s+/).filter((word) => word !== '');
  const letters = words.length === 0 ? [email.trim().charAt(0)] : words.slice(0, 2).map((word) => word.charAt(0));
  return letters.join('').toLocaleUpperCase();
}

export type Shortcut = 'palette' | 'help';

export interface KeyPress {
  key: string;
  ctrlKey: boolean;
  metaKey: boolean;
  altKey: boolean;
  typing: boolean;
}

export function shortcutOf(press: KeyPress): Shortcut | null {
  if ((press.ctrlKey || press.metaKey) && !press.altKey && press.key.toLowerCase() === 'k') {
    return 'palette';
  }
  if (!press.typing && !press.ctrlKey && !press.metaKey && !press.altKey && press.key === '?') {
    return 'help';
  }
  return null;
}
