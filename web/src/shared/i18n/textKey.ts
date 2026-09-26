import type { ParseKeys } from 'i18next';
import type { Namespace } from '@/shared/i18n/resources';

export type TextKey<N extends Namespace> = ParseKeys<[N]> & `${N}:${string}`;
