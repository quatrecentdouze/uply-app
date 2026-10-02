import auth from '@/locales/en/auth.json';
import common from '@/locales/en/common.json';
import errors from '@/locales/en/errors.json';
import incidents from '@/locales/en/incidents.json';
import monitors from '@/locales/en/monitors.json';
import organizations from '@/locales/en/organizations.json';

export const defaultNamespace = 'common';

export const resources = {
  en: { common, errors, auth, organizations, monitors, incidents },
} as const;

export type Namespace = keyof (typeof resources)['en'];
