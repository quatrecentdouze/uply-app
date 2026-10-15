import { describe, expect, it } from 'vitest';
import { readProjectFile } from '@/shared/testing/sourceFiles';

const theme = readProjectFile('src/shared/theme/theme.css');

const tokens = new Map([...theme.matchAll(/--(?:color-)?([a-z0-9-]+):\s*(#[0-9a-fA-F]{6})\b/g)].map((match) => [match[1] ?? '', match[2] ?? '']));

function token(name: string): string {
  const value = tokens.get(name);
  if (value === undefined) {
    throw new Error(`theme.css has no color token ${name}`);
  }
  return value;
}

function channels(hex: string): number[] {
  return [1, 3, 5].map((start) => Number.parseInt(hex.slice(start, start + 2), 16));
}

function linear(value: number): number {
  const ratio = value / 255;
  return ratio <= 0.04045 ? ratio / 12.92 : ((ratio + 0.055) / 1.055) ** 2.4;
}

function luminance(hex: string): number {
  const [red = 0, green = 0, blue = 0] = channels(hex).map(linear);
  return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

function contrast(first: string, second: string): number {
  const [lighter, darker] = [luminance(first), luminance(second)].sort((left, right) => right - left);
  return ((lighter ?? 0) + 0.05) / ((darker ?? 0) + 0.05);
}

function blend(color: string, background: string, alpha: number): string {
  const front = channels(color);
  const back = channels(background);
  return `#${front.map((value, index) => Math.round(alpha * value + (1 - alpha) * (back[index] ?? 0)).toString(16).padStart(2, '0')).join('')}`;
}

const normalText = 4.5;
const fieldOutline = 3;
const destructiveTint = 0.2;

const backgrounds = ['page', 'surface', 'surface-raised', 'panel', 'popover', 'chip', 'field', 'secondary'];

const textPairs: readonly [string, string][] = [
  ...['foreground', 'muted-foreground', 'quiet'].flatMap((text) => backgrounds.map((background): [string, string] => [text, background])),
  ['faint', 'surface'],
  ['faint', 'surface-raised'],
  ['faint', 'chip'],
  ['sidebar-foreground', 'sidebar'],
  ['primary', 'page'],
  ['primary', 'surface'],
  ['primary', 'panel'],
  ['primary-foreground', 'primary'],
  ['ink', 'bright'],
  ['positive', 'positive-soft'],
  ['alert', 'alert-soft'],
  ['warning', 'warning-soft'],
  ['pending', 'pending-soft'],
  ['paused', 'paused-soft'],
  ['positive', 'surface'],
  ['alert', 'surface'],
  ['warning', 'surface'],
];

const outlinePairs: readonly [string, string][] = ['field', 'page', 'surface', 'surface-raised', 'panel'].flatMap((background): [string, string][] => [
  ['field-line', background],
  ['input', background],
]);

describe('theme contrast', () => {
  it.each(textPairs)('reads %s text on %s at 4.5:1 at least', (text, background) => {
    expect(contrast(token(text), token(background))).toBeGreaterThanOrEqual(normalText);
  });

  it.each(outlinePairs)('draws the %s field outline at 3:1 at least against %s', (outline, background) => {
    expect(contrast(token(outline), token(background))).toBeGreaterThanOrEqual(fieldOutline);
  });

  it.each(['surface', 'panel', 'popover'])('reads a destructive button on its tint over %s at 4.5:1 at least', (background) => {
    const destructive = token('destructive');
    expect(contrast(destructive, blend(destructive, token(background), destructiveTint))).toBeGreaterThanOrEqual(normalText);
  });

  it('measures like the WCAG formula', () => {
    expect(contrast('#ffffff', '#000000')).toBeCloseTo(21, 5);
    expect(contrast('#777777', '#ffffff')).toBeCloseTo(4.48, 2);
  });
});
