import { readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { projectRoot, readProjectFile } from '@/shared/testing/sourceFiles';

const readme = readProjectFile('../README.md');
const manifest: unknown = JSON.parse(readProjectFile('package.json'));
const scripts = typeof manifest === 'object' && manifest !== null && 'scripts' in manifest && typeof manifest.scripts === 'object' && manifest.scripts !== null ? Object.keys(manifest.scripts) : [];

const screenshotFolder = '.github/readme';
const maxScreenshots = 8;
const maxScreenshotBytes = 300_000;

function citedImages(): string[] {
  return [...readme.matchAll(/!\[[^\]]*\]\(([^)\s]+)\)/g)].map((match) => match[1] ?? '');
}

function screenshots(): string[] {
  return readdirSync(join(projectRoot, '..', screenshotFolder)).map((name) => `${screenshotFolder}/${name}`);
}

function commandLines(): string[] {
  return [...readme.matchAll(/```bash\n([\s\S]*?)```/g)].flatMap((block) => (block[1] ?? '').split('\n').map((line) => line.trim()));
}

function citedScripts(): string[] {
  return commandLines().flatMap((line) => {
    const run = /^npm run ([a-z0-9:-]+)/.exec(line);
    if (run?.[1] !== undefined) {
      return [run[1]];
    }
    return /^npm test\b/.test(line) ? ['test'] : [];
  });
}

describe('README', () => {
  it('cites only npm scripts that exist in web', () => {
    const cited = citedScripts();

    expect(cited).toEqual(expect.arrayContaining(['dev', 'lint', 'typecheck', 'test', 'build', 'api:types', 'e2e', 'screenshots']));
    expect(cited.filter((script) => !scripts.includes(script))).toEqual([]);
  });

  it('names the dev server address of the one origin', () => {
    expect(readme).toContain('http://localhost:5173');
  });

  it('shows every screenshot of its folder and only screenshots that exist', () => {
    const cited = citedImages();
    const files = screenshots();

    expect(cited.length).toBeGreaterThan(0);
    expect(cited.filter((path) => !files.includes(path))).toEqual([]);
    expect(files.filter((path) => !cited.includes(path))).toEqual([]);
  });

  it('keeps at most 8 PNG screenshots of at most 300 KB', () => {
    const files = screenshots();

    expect(files.length).toBeLessThanOrEqual(maxScreenshots);
    expect(files.filter((path) => !path.endsWith('.png'))).toEqual([]);
    expect(files.filter((path) => statSync(join(projectRoot, '..', path)).size > maxScreenshotBytes)).toEqual([]);
  });
});
