import { describe, expect, it } from 'vitest';
import { readProjectFile } from '@/shared/testing/sourceFiles';

const readme = readProjectFile('../README.md');
const manifest: unknown = JSON.parse(readProjectFile('package.json'));
const scripts = typeof manifest === 'object' && manifest !== null && 'scripts' in manifest && typeof manifest.scripts === 'object' && manifest.scripts !== null ? Object.keys(manifest.scripts) : [];

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
});
