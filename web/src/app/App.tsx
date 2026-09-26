import { useState } from 'react';
import { I18nextProvider, useTranslation } from 'react-i18next';
import { createI18n } from '@/shared/i18n/i18n';

function AppName() {
  const { t } = useTranslation(['common']);
  return <h1 className="sr-only">{t('common:app.name')}</h1>;
}

export function App() {
  const [i18n] = useState(createI18n);
  return (
    <I18nextProvider i18n={i18n}>
      <main>
        <AppName />
      </main>
    </I18nextProvider>
  );
}
