import { describe, expect, it } from 'vitest';
import {
  createMonitorBody,
  hasChanges,
  intervalText,
  isWebUrl,
  knownMethods,
  type MonitorFormTexts,
  monitorFormSchema,
  monitorFormValues,
  statusRangesOf,
  updateMonitorBody,
} from '@/features/monitors/model/monitorForm';
import { withFieldRoots } from '@/shared/api/formFailure';
import { metaFixture, monitorFixture } from '@/shared/testing/fixtures';

const monitoring = metaFixture().monitoring;

const texts: MonitorFormTexts = {
  required: 'required',
  email: 'email',
  length: () => 'length',
  mismatch: 'mismatch',
  url: 'url',
  maxLength: (max) => `max ${String(max)}`,
  statusRanges: 'ranges',
  tooManyRanges: (max) => `too many ${String(max)}`,
  interval: 'interval',
  timeout: ({ min, max }) => `timeout ${String(min)}-${String(max)}`,
  headerName: 'token',
  headerRefused: 'refused',
  headerDuplicate: 'duplicate',
  headerValue: 'control',
  tooManyHeaders: (max) => `headers ${String(max)}`,
  threshold: ({ min, max }) => `threshold ${String(min)}-${String(max)}`,
};

function issues(values: Record<string, unknown>) {
  const result = monitorFormSchema(texts, monitoring).safeParse({ ...monitorFormValues(null, monitoring), name: 'API', url: 'https://api.example.test', ...values });
  return Object.fromEntries((result.error?.issues ?? []).map((issue) => [issue.path.join('.'), issue.message]));
}

describe('monitorFormValues', () => {
  it('starts a new monitor from the defaults of meta', () => {
    expect(monitorFormValues(null, monitoring)).toEqual({
      name: '',
      url: '',
      method: 'get',
      interval_seconds: '60',
      timeout_seconds: '10',
      accepted_status_codes: '200-299',
      follow_redirects: true,
      keyword: '',
      keyword_match: 'present',
      replace_headers: true,
      request_headers: [],
      replace_body: true,
      request_body: '',
      failure_threshold: '2',
      recovery_threshold: '2',
    });
  });

  it('fills the form from a monitor', () => {
    expect(monitorFormValues(monitorFixture({ accepted_status_codes: ['200-299', '304'], method: 'head' }), monitoring)).toMatchObject({
      method: 'head',
      accepted_status_codes: '200-299, 304',
    });
  });
});

describe('monitorFormSchema', () => {
  it('accepts a valid monitor', () => {
    expect(issues({})).toEqual({});
  });

  it('needs an http or https address', () => {
    expect(issues({ url: 'ftp://files.example.test' })).toEqual({ url: 'url' });
    expect(issues({ url: 'not a url' })).toEqual({ url: 'url' });
    expect(issues({ url: '' })).toEqual({ url: 'required' });
  });

  it('bounds the name, the interval and the timeout from meta', () => {
    expect(issues({ name: 'x'.repeat(101), interval_seconds: '45', timeout_seconds: '31' })).toEqual({
      name: 'max 100',
      interval_seconds: 'interval',
      timeout_seconds: 'timeout 1-30',
    });
    expect(issues({ timeout_seconds: '2.5' })).toEqual({ timeout_seconds: 'timeout 1-30' });
  });

  it('checks the accepted status codes', () => {
    expect(issues({ accepted_status_codes: '200-299 304' })).toEqual({});
    expect(issues({ accepted_status_codes: '299-200' })).toEqual({ accepted_status_codes: 'ranges' });
    expect(issues({ accepted_status_codes: '20x' })).toEqual({ accepted_status_codes: 'ranges' });
    expect(issues({ accepted_status_codes: ' ' })).toEqual({ accepted_status_codes: 'required' });
    expect(issues({ accepted_status_codes: Array.from({ length: 21 }, (_, index) => String(200 + index)).join(',') })).toEqual({ accepted_status_codes: 'too many 20' });
  });

  it('files the issues of the advanced section under their fields, next to the others', () => {
    expect(issues({ name: '', method: 'post', request_headers: [{ name: 'Host', value: 'a' }], failure_threshold: '0' })).toEqual({
      name: 'required',
      'request_headers.0.name': 'refused',
      failure_threshold: 'threshold 1-10',
    });
  });
});

describe('monitor bodies', () => {
  it('sends numbers and a list of ranges', () => {
    const values = { ...monitorFormValues(null, monitoring), name: ' API ', url: 'https://api.example.test ', accepted_status_codes: '200-299, 304' };

    expect(createMonitorBody(values, monitoring)).toEqual({
      name: 'API',
      url: 'https://api.example.test',
      method: 'get',
      interval_seconds: 60,
      timeout_seconds: 10,
      accepted_status_codes: ['200-299', '304'],
      follow_redirects: true,
      failure_threshold: 2,
      recovery_threshold: 2,
    });
  });

  it('leaves out an interval the contract does not list', () => {
    const values = { ...monitorFormValues(null, monitoring), name: 'API', url: 'https://api.example.test', interval_seconds: '45' };

    expect(createMonitorBody(values, monitoring)).not.toHaveProperty('interval_seconds');
    expect(updateMonitorBody(monitorFixture(), values, monitoring)).not.toHaveProperty('interval_seconds');
  });

  it('patches only what changed', () => {
    const monitor = monitorFixture();
    const values = monitorFormValues(monitor, monitoring);

    expect(hasChanges(updateMonitorBody(monitor, values, monitoring))).toBe(false);
    expect(updateMonitorBody(monitor, { ...values, name: 'Renamed', accepted_status_codes: '200-299,304' }, monitoring)).toEqual({
      name: 'Renamed',
      accepted_status_codes: ['200-299', '304'],
    });
  });

  it('adds the advanced fields to the post and the patch', () => {
    const stored = monitorFixture({ method: 'post', has_request_body: true, request_header_names: ['Authorization'] });
    const values = monitorFormValues(stored, monitoring);

    expect(createMonitorBody({ ...monitorFormValues(null, monitoring), name: 'API', url: 'https://api.example.test', keyword: 'ok' }, monitoring)).toMatchObject({
      keyword: 'ok',
      keyword_match: 'present',
    });
    expect(updateMonitorBody(stored, { ...values, method: 'get' }, monitoring)).toEqual({ method: 'get', request_body: null });
  });
});

describe('form helpers', () => {
  it('splits the status ranges on commas and spaces', () => {
    expect(statusRangesOf(' 200-299,, 304 401 ')).toEqual(['200-299', '304', '401']);
  });

  it('knows a web address', () => {
    expect(isWebUrl('http://127.0.0.1:8090/health')).toBe(true);
    expect(isWebUrl('mailto:ops@example.test')).toBe(false);
  });

  it('names an interval with its largest whole unit', () => {
    expect(intervalText(30)).toEqual({ key: 'monitors:form.every_seconds', count: 30 });
    expect(intervalText(120)).toEqual({ key: 'monitors:form.every_minutes', count: 2 });
    expect(intervalText(3600)).toEqual({ key: 'monitors:form.every_hours', count: 1 });
  });

  it('files the errors of a list under the field', () => {
    expect(withFieldRoots({ kind: 'validation', message: 'Invalid.', fields: { 'accepted_status_codes.1': ['Bad range.'], url: ['Bad URL.'] } })).toEqual({
      kind: 'validation',
      message: 'Invalid.',
      fields: { accepted_status_codes: ['Bad range.'], url: ['Bad URL.'] },
    });
  });
});

describe('knownMethods', () => {
  it('keeps the methods of meta the form can name, in their order', () => {
    expect(knownMethods(['get', 'head', 'post', 'put', 'patch'])).toEqual(['get', 'head', 'post', 'put', 'patch']);
    expect(knownMethods(['get', 'delete', 'options'])).toEqual(['get']);
  });
});
