import type { DisplayState } from '@/features/monitors/model/dashboard';
import type { MonitorListFilters } from '@/shared/api/queryKeys';
import type { Monitor } from '@/shared/api/resources';
import type { TextKey } from '@/shared/i18n/textKey';

export type StateFilter = 'all' | DisplayState;

export const stateFilters: readonly StateFilter[] = ['all', 'up', 'down', 'pending', 'paused'];

export const stateFilterTextKeys: Record<StateFilter, TextKey<'monitors'>> = {
  all: 'monitors:list.filters.all',
  up: 'monitors:states.up',
  down: 'monitors:states.down',
  pending: 'monitors:states.pending',
  paused: 'monitors:states.paused',
};

export function listFilters(state: StateFilter, search: string, perPage: number): MonitorListFilters {
  const words = search.trim();
  return {
    ...(state === 'all' ? {} : { state }),
    ...(words === '' ? {} : { search: words }),
    perPage,
  };
}

export type ListEmptiness = 'none' | 'filtered' | 'first';

export function emptinessOf(monitors: readonly Monitor[], filters: MonitorListFilters): ListEmptiness {
  if (monitors.length > 0) {
    return 'none';
  }
  return filters.state === undefined && filters.search === undefined ? 'first' : 'filtered';
}

export type PauseAction = 'pause' | 'resume';

export function pauseActionOf(monitor: Pick<Monitor, 'is_paused'>): PauseAction {
  return monitor.is_paused ? 'resume' : 'pause';
}
