import type { TextKey } from '@/shared/i18n/textKey';

export interface TitledMatch {
  status: 'pending' | 'success' | 'error' | 'notFound';
  _notFound?: boolean | undefined;
  staticData: { title?: TextKey<'common'> };
}

export function routeTitleKey(matches: readonly TitledMatch[]): TextKey<'common'> | null {
  if (matches.some((match) => match.status === 'notFound' || match._notFound === true)) {
    return 'common:not_found.title';
  }
  if (matches.some((match) => match.status === 'error')) {
    return 'common:route_error.title';
  }
  const titled = matches.flatMap((match) => (match.staticData.title === undefined ? [] : [match.staticData.title]));
  return titled.at(-1) ?? null;
}
