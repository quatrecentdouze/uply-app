import { describe, expect, it } from 'vitest';
import { routeTitleKey, type TitledMatch } from '@/app/model/titles';

function match(overrides: Partial<TitledMatch> = {}): TitledMatch {
  return { status: 'success', staticData: {}, ...overrides };
}

describe('routeTitleKey', () => {
  it('takes the title of the deepest route that has one', () => {
    const matches = [match(), match({ staticData: { title: 'common:pages.settings' } }), match({ staticData: { title: 'common:titles.security' } })];

    expect(routeTitleKey(matches)).toBe('common:titles.security');
  });

  it('names a page that was not found or failed', () => {
    expect(routeTitleKey([match({ _notFound: true }), match({ staticData: { title: 'common:pages.monitors' } })])).toBe('common:not_found.title');
    expect(routeTitleKey([match(), match({ status: 'error', staticData: { title: 'common:pages.monitors' } })])).toBe('common:route_error.title');
  });

  it('has no title without a titled route', () => {
    expect(routeTitleKey([match()])).toBeNull();
  });
});
