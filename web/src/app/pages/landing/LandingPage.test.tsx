import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { z } from 'zod';
import { LandingPage } from '@/app/pages/landing/LandingPage';
import texts from '@/locales/en/landing.json';
import { readProjectFile } from '@/shared/testing/sourceFiles';
import { renderPage } from '@/shared/testing/renderPage';

const enumSchema = z.looseObject({ enum: z.array(z.unknown()).optional() });

const openApiSchema = z.looseObject({
  components: z.looseObject({
    schemas: z.record(z.string(), z.looseObject({ enum: z.array(z.unknown()).optional(), properties: z.record(z.string(), enumSchema).optional() })),
  }),
});

const openApi = openApiSchema.parse(JSON.parse(readProjectFile('../api/openapi.json')));
const monitoringConfig = readProjectFile('../api/config/monitoring.php');
const statusPagesConfig = readProjectFile('../api/config/status_pages.php');

function enumValuesOf(schema: string, property?: string): unknown[] {
  const entry = openApi.components.schemas[schema];
  return (property === undefined ? entry?.enum : entry?.properties?.[property]?.enum) ?? [];
}

function enumOf(schema: string, property?: string): string[] {
  return enumValuesOf(schema, property).filter((value): value is string => typeof value === 'string');
}

function phpNumber(source: string, key: string): number {
  const match = new RegExp(`'${key}' => (\\d+)`).exec(source);
  return Number(match?.[1]);
}

function phpNumbers(source: string, key: string): number[] {
  const match = new RegExp(`'${key}' => \\[([^\\]]*)\\]`).exec(source);
  return (match?.[1] ?? '').split(',').map((value) => Number(value.trim()));
}

function numbersIn(text: string): number[] {
  return [...text.matchAll(/\d+/g)].map((match) => Number(match[0]));
}

const secondsPerMinute = 60;
const secondsPerHour = 3600;

function intervalNumbers(intervals: readonly number[]): number[] {
  return intervals.flatMap((seconds) => {
    if (seconds < secondsPerMinute) {
      return [seconds];
    }
    if (seconds % secondsPerHour === 0) {
      return seconds === secondsPerHour ? [] : [seconds / secondsPerHour];
    }
    return [seconds / secondsPerMinute];
  });
}

const faq = texts.faq.items;

describe('landing texts against the API', () => {
  it('lists exactly the check intervals the API allows', () => {
    const allowed = phpNumbers(monitoringConfig, 'allowed_seconds');

    expect(allowed).toEqual(enumValuesOf('StoreMonitorRequest', 'interval_seconds'));
    expect(new Set(numbersIn(faq.intervals.answer))).toEqual(new Set(intervalNumbers(allowed)));
    expect(allowed).toContain(secondsPerHour);
    expect(faq.intervals.answer).toContain('every hour');
    expect(phpNumber(monitoringConfig, 'default_seconds')).toBe(secondsPerMinute);
    expect(faq.intervals.answer).toContain('One minute is the default');
  });

  it('names the methods, the accepted codes and the timeouts of the checks', () => {
    for (const method of enumOf('HttpMethod')) {
      expect(faq.checks.answer).toContain(method.toUpperCase());
    }
    expect(monitoringConfig).toContain("'accepted_status_codes' => ['200-299']");
    expect(faq.checks.answer).toContain('200 to 299');
    expect(faq.checks.answer).toContain(`${String(phpNumber(monitoringConfig, 'default_timeout_seconds'))} seconds by default`);
    expect(faq.checks.answer).toContain(`${String(phpNumber(monitoringConfig, 'max_timeout_seconds'))} seconds at most`);
  });

  it('describes the confirmation and every alert channel', () => {
    expect(faq.alerts.answer).toContain(`By default when ${String(phpNumber(monitoringConfig, 'failures_to_confirm_down'))} checks in a row fail`);
    expect(faq.alerts.answer).toContain(`when ${String(phpNumber(monitoringConfig, 'successes_to_confirm_up'))} checks in a row pass`);
    expect(faq.alerts.answer).toContain(`after ${String(phpNumber(monitoringConfig, 'recheck_delay_seconds'))} seconds`);
    expect(texts.features.checks.text).toContain(`${String(phpNumber(monitoringConfig, 'recheck_delay_seconds'))} seconds later`);
    for (const channel of enumOf('ChannelType')) {
      expect(faq.alerts.answer.toLowerCase()).toContain(channel);
    }
  });

  it('gives the thresholds of a monitor and the days of the certificate alert', () => {
    const thresholds = `${String(phpNumber(monitoringConfig, 'min_threshold'))} to ${String(phpNumber(monitoringConfig, 'max_threshold'))}`;
    const days = phpNumbers(monitoringConfig, 'expiry_alert_days');
    const dayList = `${days.slice(0, -1).join(', ')} and ${String(days.at(-1))} days`;

    expect(faq.alerts.answer).toContain(`can ask for ${thresholds} instead`);
    expect(texts.features.checks.text).toContain(`after ${thresholds} of your choice`);
    expect(texts.features.checks.text).toContain(`two results in a row by default`);
    expect(phpNumber(monitoringConfig, 'failures_to_confirm_down')).toBe(2);
    expect(faq.alerts.answer).toContain(`also ${dayList} before the TLS certificate`);
    expect(texts.features.certificates.text).toContain(`${dayList} before the TLS certificate`);
  });

  it('names the request parts and the keyword rules of a check', () => {
    for (const method of enumOf('HttpMethod')) {
      expect(texts.features.requests.text).toContain(method.toUpperCase());
    }
    expect(enumOf('KeywordMatch')).toEqual(['present', 'absent']);
    expect(texts.features.requests.text).toContain('must or must not be in the answer');
    expect(faq.checks.answer).toContain('the headers and body you set');
    expect(faq.checks.answer).toContain('A keyword can also have to be in the answer, or not be in it.');
  });

  it('gives the bar days and incident days of the public status pages', () => {
    expect(faq.status_pages.answer).toContain(`last ${String(phpNumber(statusPagesConfig, 'bar_days'))} days`);
    expect(faq.status_pages.answer).toContain(`last ${String(phpNumber(statusPagesConfig, 'incident_days'))} days`);
    expect(texts.features.status_pages.text).toContain(`${String(phpNumber(statusPagesConfig, 'bar_days'))} days`);
  });

  it('names the four roles', () => {
    for (const role of enumOf('Role')) {
      expect(faq.teams.answer).toContain(role);
      expect(texts.features.teams.text).toContain(role);
    }
  });
});

describe('LandingPage', () => {
  it('shows the sections, the anchors and the two calls to action', async () => {
    renderPage(() => <LandingPage />, { path: '/' });

    expect(await screen.findByRole('heading', { level: 1, name: /Your websites and APIs/ })).toBeInTheDocument();
    const sections = screen.getByRole('navigation', { name: 'Sections' });
    expect(within(sections).getByRole('link', { name: 'Features' })).toHaveAttribute('href', '#features');
    expect(within(sections).getByRole('link', { name: 'FAQ' })).toHaveAttribute('href', '#faq');
    expect(document.getElementById('features')).not.toBeNull();
    expect(document.getElementById('faq')).not.toBeNull();
    expect(screen.getAllByRole('link', { name: /Get started/ }).every((link) => link.getAttribute('href') === '/register')).toBe(true);
    expect(screen.getAllByRole('link', { name: 'Sign in' }).every((link) => link.getAttribute('href') === '/login')).toBe(true);
    expect(screen.getByRole('heading', { name: /From a URL/ })).toBeInTheDocument();
    expect(screen.getAllByRole('group')).toHaveLength(Object.keys(faq).length);
  });

  it('keeps the sample previews out of the keyboard and the screen reader', async () => {
    const { view } = renderPage(() => <LandingPage />, { path: '/' });

    await screen.findByRole('heading', { level: 1, name: /Your websites and APIs/ });
    const previews = view.container.querySelectorAll('[inert]');
    expect(previews.length).toBeGreaterThan(0);
    expect(screen.getByText('Sample data. Every card above is a real component of Uply.')).toBeInTheDocument();
  });
});
