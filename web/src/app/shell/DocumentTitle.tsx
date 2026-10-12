import { useMatches } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { routeTitleKey } from '@/app/model/titles';
import { useDocumentTitle } from '@/shared/ui/use-document-title';

export function DocumentTitle() {
  const { t } = useTranslation(['common']);
  const key = useMatches({ select: routeTitleKey });
  useDocumentTitle(key === null ? t('common:app.name') : t('common:titles.document', { page: t(key) }));
  return null;
}
