import { describe, expect, it } from 'vitest';
import { type SourceFile, sourceFiles } from '@/shared/testing/sourceFiles';

const generatedFiles = new Set([
  'src/shared/api/schema.d.ts',
  'src/shared/ui/alert-dialog.tsx',
  'src/shared/ui/alert.tsx',
  'src/shared/ui/badge.tsx',
  'src/shared/ui/button.tsx',
  'src/shared/ui/chart.tsx',
  'src/shared/ui/checkbox.tsx',
  'src/shared/ui/command.tsx',
  'src/shared/ui/dialog.tsx',
  'src/shared/ui/dropdown-menu.tsx',
  'src/shared/ui/input-group.tsx',
  'src/shared/ui/input.tsx',
  'src/shared/ui/label.tsx',
  'src/shared/ui/native-select.tsx',
  'src/shared/ui/separator.tsx',
  'src/shared/ui/sheet.tsx',
  'src/shared/ui/skeleton.tsx',
  'src/shared/ui/switch.tsx',
  'src/shared/ui/table.tsx',
  'src/shared/ui/tabs.tsx',
  'src/shared/ui/textarea.tsx',
  'src/shared/ui/tooltip.tsx',
]);

const isolatedPackages: readonly { modules: RegExp; home: readonly string[] }[] = [
  { modules: /^openapi-fetch$/, home: ['src/shared/api/'] },
  { modules: /^(laravel-echo|pusher-js)$/, home: ['src/shared/realtime/'] },
  { modules: /^recharts$/, home: ['src/shared/ui/chart'] },
  { modules: /^i18next$/, home: ['src/shared/i18n/'] },
  { modules: /^(radix-ui|@radix-ui\/.+|cmdk|class-variance-authority)$/, home: ['src/shared/ui/'] },
  { modules: /^lucide-react$/, home: ['src/shared/ui/'] },
];

type Layer = { kind: 'shared' } | { kind: 'feature'; name: string } | { kind: 'app' } | { kind: 'entry' } | { kind: 'other' };

function layerOf(path: string): Layer {
  if (path.startsWith('src/shared/')) {
    return { kind: 'shared' };
  }
  if (path.startsWith('src/features/')) {
    return { kind: 'feature', name: path.split('/')[2] ?? '' };
  }
  if (path.startsWith('src/app/')) {
    return { kind: 'app' };
  }
  return path === 'src/main.tsx' ? { kind: 'entry' } : { kind: 'other' };
}

function isTest(path: string): boolean {
  return /\.test\.tsx?$/.test(path) || path.startsWith('src/shared/testing/') || path.startsWith('src/app/testing/');
}

const importPattern = /(?:\bfrom\s+|\bimport\s+|\bimport\()['"]([^'"]+)['"]/g;

function importsOf(file: SourceFile): string[] {
  return [...file.content.matchAll(importPattern)].map((match) => match[1] ?? '');
}

function layerAllows(from: Layer, target: Layer): boolean {
  switch (from.kind) {
    case 'shared':
      return target.kind === 'shared' || target.kind === 'other';
    case 'feature':
      return target.kind === 'shared' || target.kind === 'other' || (target.kind === 'feature' && target.name === from.name);
    default:
      return true;
  }
}

export function forbiddenImports(file: SourceFile): string[] {
  const from = layerOf(file.path);
  return importsOf(file).flatMap((module) => {
    if (module.startsWith('@/')) {
      const target = layerOf(module.replace('@/', 'src/') + '/');
      return layerAllows(from, target) ? [] : [`${file.path} imports ${module}`];
    }
    if (module.startsWith('.')) {
      return [`${file.path} uses the relative import ${module}`];
    }
    const rule = isolatedPackages.find((candidate) => candidate.modules.test(module));
    if (rule === undefined || isTest(file.path) || rule.home.some((home) => file.path.startsWith(home))) {
      return [];
    }
    return [`${file.path} imports ${module} outside ${rule.home.join(', ')}`];
  });
}

const seamsTest = 'src/app/seams.test.ts';
const ownFiles = sourceFiles('src').filter((file) => !generatedFiles.has(file.path) && file.path !== seamsTest);
const productionFiles = ownFiles.filter((file) => !isTest(file.path));

function offending(files: readonly SourceFile[], pattern: RegExp): string[] {
  return files.filter((file) => pattern.test(file.content)).map((file) => file.path);
}

describe('seams', () => {
  it('keeps features apart and shared below them, and each third party in its module', () => {
    expect(sourceFiles('src').flatMap(forbiddenImports)).toEqual([]);
  });

  it('calls fetch only in the api module', () => {
    expect(offending(productionFiles.filter((file) => !file.path.startsWith('src/shared/api/')), /\bfetch\(/)).toEqual([]);
  });

  it('touches localStorage only in the storage module', () => {
    expect(offending(productionFiles.filter((file) => !file.path.startsWith('src/shared/storage/')), /\blocalStorage\b/)).toEqual([]);
  });

  it('never uses any', () => {
    expect(offending(ownFiles, /(:\s*any\b|<any>|\bas\s+any\b|\bany\[\])/)).toEqual([]);
  });

  it('writes no color in a component outside the theme', () => {
    const components = ownFiles.filter((file) => file.path.endsWith('.tsx') && !file.path.startsWith('src/shared/theme/'));
    expect(offending(components, /#[0-9a-fA-F]{3,8}\b|\b(rgba?|hsla?|oklch)\(/)).toEqual([]);
  });

  it('writes no comment in our own files', () => {
    expect(offending(ownFiles, /(^|\s)\/\/|\/\*/m)).toEqual([]);
  });
});

describe('forbiddenImports', () => {
  it('catches a feature that imports another one', () => {
    const planted = { path: 'src/features/monitors/api/monitors.ts', content: "import { useMe } from '@/features/auth/api/session';" };

    expect(forbiddenImports(planted)).toEqual(['src/features/monitors/api/monitors.ts imports @/features/auth/api/session']);
  });

  it('catches shared code that reaches the app and a third party out of its module', () => {
    const planted = { path: 'src/shared/ui/brand.tsx', content: "import { router } from '@/app/router';\nimport Echo from 'laravel-echo';" };

    expect(forbiddenImports(planted)).toHaveLength(2);
  });
});
