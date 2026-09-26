import common from '@/locales/en/common.json';

export const defaultNamespace = 'common';

export const resources = {
  en: { common },
} as const;

export type Namespace = keyof (typeof resources)['en'];
