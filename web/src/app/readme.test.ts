import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { projectRoot } from '@/shared/testing/sourceFiles';

const screenshotFolder = join(projectRoot, '..', '.github', 'readme');
const screenshotNames = ['dashboard.png', 'monitor.png', 'status-page.png'];
const maxScreenshotBytes = 300_000;
const pngSignature = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];

function screenshotPath(name: string): string {
  return join(screenshotFolder, name);
}

describe('README screenshots', () => {
  it('keeps exactly the three screenshots of the README', () => {
    expect(readdirSync(screenshotFolder).sort()).toEqual(screenshotNames);
  });

  it('keeps each screenshot a PNG of at most 300 KB', () => {
    for (const name of screenshotNames) {
      expect([...readFileSync(screenshotPath(name)).subarray(0, pngSignature.length)]).toEqual(pngSignature);
      expect(statSync(screenshotPath(name)).size).toBeLessThanOrEqual(maxScreenshotBytes);
    }
  });
});
