import { describe, expect, it } from 'vitest';
import shipped from '@/features/status-pages/model/publicPageSettings.json';
import { parsePublicPageSettings, publicPageSettings } from '@/features/status-pages/model/publicPageSettings';

describe('public page settings', () => {
  it('loads the shipped file', () => {
    expect(publicPageSettings.compact_bars).toBe(30);
  });

  it('names the file and the key of a wrong value', () => {
    expect(() => parsePublicPageSettings({ ...shipped, compact_bars: 0 })).toThrow(/publicPageSettings\.json: compact_bars/);
  });
});
