import { describe, expect, it } from 'vitest';
import { pageOfPath } from '@/app/model/pages';

describe('pageOfPath', () => {
  it.each([
    ['/', 'dashboard'],
    ['/monitors', 'monitors'],
    ['/monitors/01jbx', 'monitors'],
    ['/incidents/01jbx', 'incidents'],
    ['/status-pages', 'status_pages'],
    ['/alert-channels', 'alert_channels'],
    ['/settings/security', 'settings'],
  ])('reads the page of %s', (path, page) => {
    expect(pageOfPath(path)).toBe(page);
  });

  it('knows no page for an unknown section', () => {
    expect(pageOfPath('/nowhere')).toBeNull();
  });
});
