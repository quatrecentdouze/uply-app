import { useTranslation } from 'react-i18next';
import { type DisplayState, stateTextKeys } from '@/features/monitors/model/dashboard';
import { Pill, type PillTone } from '@/shared/ui/surface-card';

const stateTones: Record<DisplayState, PillTone> = {
  up: 'positive',
  down: 'alert',
  pending: 'pending',
  paused: 'paused',
};

export function MonitorStatePill({ state }: { state: DisplayState }) {
  const { t } = useTranslation(['monitors']);
  return <Pill tone={stateTones[state]}>{t(stateTextKeys[state])}</Pill>;
}
