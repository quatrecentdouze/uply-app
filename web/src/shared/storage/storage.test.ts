import { beforeEach, describe, expect, it } from 'vitest';
import { browserStore, storageKeys, webStore } from '@/shared/storage/storage';

describe('webStore', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('reads back what it wrote', () => {
    browserStore.write(storageKeys.organization, '01jbx');

    expect(browserStore.read(storageKeys.organization)).toBe('01jbx');
  });

  it('removes a key written as null', () => {
    browserStore.write(storageKeys.organization, '01jbx');
    browserStore.write(storageKeys.organization, null);

    expect(window.localStorage.getItem(storageKeys.organization)).toBeNull();
  });

  it('answers null and never throws when the storage is blocked', () => {
    const blocked = webStore(() => {
      throw new DOMException('blocked', 'SecurityError');
    });

    expect(blocked.read(storageKeys.organization)).toBeNull();
    expect(() => {
      blocked.write(storageKeys.organization, 'x');
    }).not.toThrow();
  });
});
