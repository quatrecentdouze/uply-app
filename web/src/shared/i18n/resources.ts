import alerting from '@/locales/en/alerting.json';
import auth from '@/locales/en/auth.json';
import common from '@/locales/en/common.json';
import errors from '@/locales/en/errors.json';
import incidents from '@/locales/en/incidents.json';
import landing from '@/locales/en/landing.json';
import monitors from '@/locales/en/monitors.json';
import organizations from '@/locales/en/organizations.json';
import statusPages from '@/locales/en/status-pages.json';

export const defaultNamespace = 'common';

export const resources = {
  en: { common, errors, auth, organizations, monitors, incidents, alerting, 'status-pages': statusPages, landing },
} as const;

export type Namespace = keyof (typeof resources)['en'];
