import { describe, expect, it } from 'vitest';
import { readProjectFile } from '@/shared/testing/sourceFiles';

const theme = readProjectFile('src/shared/theme/theme.css');

function block(media: string): string {
  const start = theme.indexOf(`@media (${media})`);
  return start === -1 ? '' : theme.slice(start, theme.indexOf('\n}\n', start));
}

describe('motion', () => {
  it('stops animations, transitions and smooth scrolling when the system asks for reduced motion', () => {
    const reduced = block('prefers-reduced-motion: reduce');

    expect(reduced).toContain('animation-duration: 0.01ms !important');
    expect(reduced).toContain('transition-duration: 0.01ms !important');
    expect(reduced).toContain('scroll-behavior: auto !important');
  });

  it('scrolls smoothly to an anchor only when motion is welcome', () => {
    expect(block('prefers-reduced-motion: no-preference')).toContain('scroll-behavior: smooth');
  });
});
