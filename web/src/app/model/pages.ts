import type { TextKey } from '@/shared/i18n/textKey';

export type PageId = 'dashboard' | 'monitors' | 'incidents' | 'status_pages' | 'alert_channels' | 'settings' | 'help';

export const pageTitleKeys: Record<PageId, TextKey<'common'>> = {
  dashboard: 'common:pages.dashboard',
  monitors: 'common:pages.monitors',
  incidents: 'common:pages.incidents',
  status_pages: 'common:pages.status_pages',
  alert_channels: 'common:pages.alert_channels',
  settings: 'common:pages.settings',
  help: 'common:pages.help',
};
