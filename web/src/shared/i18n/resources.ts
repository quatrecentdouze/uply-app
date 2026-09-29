import auth from '@/locales/en/auth.json';
import common from '@/locales/en/common.json';
import errors from '@/locales/en/errors.json';

export const defaultNamespace = 'common';

export const resources = {
  en: { common, errors, auth },
} as const;

export type Namespace = keyof (typeof resources)['en'];
