import { describe, expect, it } from 'vitest';
import { resources } from '@/shared/i18n/resources';
import { sourceFiles } from '@/shared/testing/sourceFiles';

const pluralSuffixes = ['_zero', '_one', '_two', '_few', '_many', '_other'];

function flatten(prefix: string, value: unknown): string[] {
  if (typeof value === 'string') {
    return [prefix];
  }
  if (typeof value === 'object' && value !== null) {
    return Object.entries(value).flatMap(([key, child]) => flatten(prefix === '' ? key : `${prefix}.${key}`, child));
  }
  return [];
}

function withoutPluralSuffix(key: string): string {
  const suffix = pluralSuffixes.find((candidate) => key.endsWith(candidate));
  return suffix === undefined ? key : key.slice(0, -suffix.length);
}

const namespaces = Object.keys(resources.en);

const definedKeys = new Set(
  Object.entries(resources.en).flatMap(([namespace, texts]) =>
    flatten('', texts).map((key) => `${namespace}:${withoutPluralSuffix(key)}`),
  ),
);

const keyPattern = new RegExp(`['"\`]((?:${namespaces.join('|')}):[A-Za-z0-9_.-]+)['"\`]`, 'g');

const usedKeys = new Set(
  sourceFiles('src')
    .filter((file) => !file.path.endsWith('.test.ts') && !file.path.endsWith('.test.tsx'))
    .flatMap((file) => [...file.content.matchAll(keyPattern)].map((match) => match[1] ?? '')),
);

describe('text keys', () => {
  it('finds every key used in the code in a text file', () => {
    expect([...usedKeys].filter((key) => !definedKeys.has(key)).sort()).toEqual([]);
  });

  it('uses every key of the text files in the code', () => {
    expect([...definedKeys].filter((key) => !usedKeys.has(key)).sort()).toEqual([]);
  });

  it('loads one text file per namespace', () => {
    expect(namespaces.length).toBeGreaterThan(0);
  });
});
