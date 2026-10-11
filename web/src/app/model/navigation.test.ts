import { describe, expect, it } from 'vitest';
import { badgeText, initialsOf, navigationGroups, navigationLinks, settingsSections, shortcutOf } from '@/app/model/navigation';

const press = { key: 'k', ctrlKey: false, metaKey: false, altKey: false, typing: false };

describe('navigationGroups', () => {
  it('lists the groups and pages of the app shell in order', () => {
    expect(navigationGroups.map((group) => group.id)).toEqual(['monitoring', 'alerting', 'general']);
    expect(navigationLinks().map((link) => link.to)).toEqual(['/dashboard', '/monitors', '/incidents', '/status-pages', '/alert-channels', '/settings']);
  });

  it('puts the open incidents badge on incidents only', () => {
    expect(navigationLinks().filter((link) => link.badge !== null).map((link) => link.page)).toEqual(['incidents']);
  });
});

describe('badgeText', () => {
  it('hides an empty count and caps a large one', () => {
    expect(badgeText(null)).toBeNull();
    expect(badgeText(0)).toBeNull();
    expect(badgeText(4)).toBe('4');
    expect(badgeText(250)).toBe('99+');
  });
});

describe('initialsOf', () => {
  it('takes the first letters of two words, or of the email', () => {
    expect(initialsOf('Demo User', 'demo@uply.test')).toBe('DU');
    expect(initialsOf('ada  lovelace byron', 'ada@example.test')).toBe('AL');
    expect(initialsOf('   ', 'viewer@uply.test')).toBe('V');
  });
});

describe('shortcutOf', () => {
  it('opens the palette with Ctrl K or Cmd K, even while typing', () => {
    expect(shortcutOf({ ...press, ctrlKey: true })).toBe('palette');
    expect(shortcutOf({ ...press, key: 'K', metaKey: true, typing: true })).toBe('palette');
    expect(shortcutOf(press)).toBeNull();
  });

  it('opens the help with ? outside a field', () => {
    expect(shortcutOf({ ...press, key: '?' })).toBe('help');
    expect(shortcutOf({ ...press, key: '?', typing: true })).toBeNull();
  });
});

describe('settingsSections', () => {
  it('lists the four settings tabs in order', () => {
    expect(settingsSections.map((section) => section.to)).toEqual(['/settings/organization', '/settings/members', '/settings/profile', '/settings/security']);
  });
});
