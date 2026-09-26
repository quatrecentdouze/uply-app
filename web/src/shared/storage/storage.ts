export const storageKeys = {
  organization: 'uply.organization',
  sidebarCollapsed: 'uply.sidebar-collapsed',
} as const;

export type StorageKey = (typeof storageKeys)[keyof typeof storageKeys];

export interface KeyValueStore {
  read(key: StorageKey): string | null;
  write(key: StorageKey, value: string | null): void;
}

export function webStore(backend: () => Storage): KeyValueStore {
  return {
    read(key) {
      try {
        return backend().getItem(key);
      } catch {
        return null;
      }
    },
    write(key, value) {
      try {
        if (value === null) {
          backend().removeItem(key);
        } else {
          backend().setItem(key, value);
        }
      } catch {
        return;
      }
    },
  };
}

export const browserStore: KeyValueStore = webStore(() => window.localStorage);
