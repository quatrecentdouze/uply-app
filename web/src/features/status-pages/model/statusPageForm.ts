import { z } from 'zod';
import type { CreateStatusPageBody, UpdateStatusPageBody } from '@/features/status-pages/api/statusPages';
import type { Meta } from '@/shared/api/meta';
import type { StatusPage } from '@/shared/api/resources';
import type { BaseValidationTexts } from '@/shared/i18n/useValidationTexts';

export type StatusPagesMeta = Meta['status_pages'];

export interface StatusPageFormTexts extends BaseValidationTexts {
  maxLength: (max: number) => string;
  slug: (bounds: { min: number; max: number }) => string;
  tooManyMonitors: (max: number) => string;
}

export interface PageMonitorValue {
  monitor_id: string;
  display_name: string;
}

export interface StatusPageFormValues {
  title: string;
  slug: string;
  description: string;
  is_published: boolean;
  monitors: PageMonitorValue[];
}

export interface MonitorOption {
  id: string;
  name: string;
}

export function slugFrom(title: string, maxLength: number): string {
  return title
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, maxLength)
    .replace(/-+$/g, '');
}

function slugField(texts: StatusPageFormTexts, meta: StatusPagesMeta) {
  const { slug } = meta.lengths;
  const pattern = new RegExp(slug.pattern);
  return z
    .string()
    .trim()
    .refine((value) => value.length >= slug.min && value.length <= slug.max && pattern.test(value), texts.slug({ min: slug.min, max: slug.max }));
}

function titleField(texts: StatusPageFormTexts, meta: StatusPagesMeta) {
  const { title } = meta.lengths;
  return z.string().trim().min(1, texts.required).max(title.max, texts.maxLength(title.max));
}

export function newStatusPageSchema(texts: StatusPageFormTexts, meta: StatusPagesMeta) {
  return z.object({ title: titleField(texts, meta), slug: slugField(texts, meta) });
}

export type NewStatusPageValues = z.infer<ReturnType<typeof newStatusPageSchema>>;

export function statusPageSchema(texts: StatusPageFormTexts, meta: StatusPagesMeta) {
  const { description, display_name: displayName } = meta.lengths;
  return z.object({
    title: titleField(texts, meta),
    slug: slugField(texts, meta),
    description: z.string().max(description.max, texts.maxLength(description.max)),
    is_published: z.boolean(),
    monitors: z
      .array(z.object({ monitor_id: z.string(), display_name: z.string().max(displayName.max, texts.maxLength(displayName.max)) }))
      .max(meta.limits.max_monitors_per_page, texts.tooManyMonitors(meta.limits.max_monitors_per_page)),
  });
}

export function statusPageFormValues(page: StatusPage): StatusPageFormValues {
  return {
    title: page.title,
    slug: page.slug,
    description: page.description ?? '',
    is_published: page.is_published,
    monitors: [...page.monitors].sort((left, right) => left.position - right.position).map((monitor) => ({ monitor_id: monitor.monitor_id, display_name: monitor.display_name })),
  };
}

export function createStatusPageBody(values: NewStatusPageValues): CreateStatusPageBody {
  return { title: values.title.trim(), slug: values.slug.trim(), is_published: false };
}

function monitorsBody(monitors: readonly PageMonitorValue[]) {
  return monitors.map((monitor) => {
    const name = monitor.display_name.trim();
    return name === '' ? { monitor_id: monitor.monitor_id } : { monitor_id: monitor.monitor_id, display_name: name };
  });
}

function sameMonitors(left: readonly PageMonitorValue[], right: readonly PageMonitorValue[]): boolean {
  return left.length === right.length && left.every((monitor, index) => monitor.monitor_id === right[index]?.monitor_id && monitor.display_name.trim() === right[index].display_name.trim());
}

export function updateStatusPageBody(page: StatusPage, values: StatusPageFormValues): UpdateStatusPageBody {
  const current = statusPageFormValues(page);
  const title = values.title.trim();
  const slug = values.slug.trim();
  const description = values.description.trim();
  return {
    ...(title === current.title ? {} : { title }),
    ...(slug === current.slug ? {} : { slug }),
    ...(description === current.description ? {} : { description: description === '' ? null : description }),
    ...(values.is_published === current.is_published ? {} : { is_published: values.is_published }),
    ...(sameMonitors(values.monitors, current.monitors) ? {} : { monitors: monitorsBody(values.monitors) }),
  };
}

export function availableMonitors(options: readonly MonitorOption[], selected: readonly PageMonitorValue[]): MonitorOption[] {
  const taken = new Set(selected.map((monitor) => monitor.monitor_id));
  return options.filter((option) => !taken.has(option.id));
}

export function monitorName(options: readonly MonitorOption[], monitorId: string): string | null {
  return options.find((option) => option.id === monitorId)?.name ?? null;
}
