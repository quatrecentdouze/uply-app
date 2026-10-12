import { describe, expect, it } from 'vitest';
import { queryKeys } from '@/shared/api/queryKeys';

function startsWith(key: readonly unknown[], prefix: readonly unknown[]): boolean {
  return prefix.every((part, index) => key[index] === part);
}

describe('queryKeys', () => {
  it('nests every monitor key under the organization', () => {
    const organization = queryKeys.organization('org-1');

    expect(startsWith(queryKeys.monitors.list('org-1', { state: 'down' }), queryKeys.monitors.lists('org-1'))).toBe(true);
    expect(startsWith(queryKeys.monitors.detail('org-1', 'mon-1'), queryKeys.monitors.all('org-1'))).toBe(true);
    expect(startsWith(queryKeys.overview.window('org-1', '7d'), organization)).toBe(true);
    expect(startsWith(queryKeys.incidents.list('org-1', { state: 'open' }), queryKeys.incidents.lists('org-1'))).toBe(true);
    expect(startsWith(queryKeys.monitors.statsWindow('org-1', 'mon-1', '7d'), queryKeys.monitors.stats('org-1', 'mon-1'))).toBe(true);
    expect(startsWith(queryKeys.monitors.checks('org-1', 'mon-1'), queryKeys.monitors.all('org-1'))).toBe(true);
    expect(startsWith(queryKeys.alertChannels.ofMonitor('org-1', 'mon-1'), queryKeys.alertChannels.all('org-1'))).toBe(true);
    expect(startsWith(queryKeys.statusPages.detail('org-1', 'page-1'), organization)).toBe(true);
    expect(startsWith(queryKeys.members('org-1'), organization)).toBe(true);
  });

  it('keeps two organizations apart', () => {
    expect(startsWith(queryKeys.monitors.lists('org-2'), queryKeys.organization('org-1'))).toBe(false);
  });

  it('keeps a public status page out of any organization scope', () => {
    expect(startsWith(queryKeys.publicStatusPage('demo-services'), ['organizations'])).toBe(false);
  });

  it('keeps the organizations list out of any organization scope', () => {
    expect(startsWith(queryKeys.organizations(), ['organizations'])).toBe(false);
  });
});
