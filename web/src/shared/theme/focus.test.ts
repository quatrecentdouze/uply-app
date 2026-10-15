import { describe, expect, it } from 'vitest';
import { readProjectFile } from '@/shared/testing/sourceFiles';

const theme = readProjectFile('src/shared/theme/theme.css');

describe('focus ring', () => {
  it('draws a solid ring in the ring color around every focused control', () => {
    const start = theme.indexOf(':where(a, button, summary');
    const rule = theme.slice(start, theme.indexOf('}', start));

    expect(start).toBeGreaterThan(-1);
    expect(rule).toContain(':focus-visible');
    expect(rule).toContain('outline: 2px solid var(--ring)');
    expect(rule).toContain('outline-offset: 2px');
  });
});
