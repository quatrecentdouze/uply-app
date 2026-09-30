import i18next, { type i18n } from 'i18next';
import { initReactI18next } from 'react-i18next';
import { defaultNamespace, resources } from '@/shared/i18n/resources';

export type I18n = i18n;

export const defaultLanguage = 'en';

export function createI18n(): i18n {
  const instance = i18next.createInstance();
  void instance.use(initReactI18next).init({
    lng: defaultLanguage,
    fallbackLng: defaultLanguage,
    resources,
    defaultNS: defaultNamespace,
    ns: Object.keys(resources[defaultLanguage]),
    interpolation: { escapeValue: false },
    initAsync: false,
    returnNull: false,
  });
  return instance;
}
