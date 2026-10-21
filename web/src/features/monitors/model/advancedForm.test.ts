import { describe, expect, it } from 'vitest';
import {
  advancedCreateBody,
  advancedIssues,
  advancedOpenAtStart,
  type AdvancedTexts,
  advancedUpdateBody,
  type AdvancedValues,
  advancedValues,
  bodyApplies,
  keywordApplies,
  knownKeywordMatches,
  methodNotes,
  replacingHeaderRows,
  touchesAdvanced,
} from '@/features/monitors/model/advancedForm';
import { metaFixture, monitorFixture } from '@/shared/testing/fixtures';

const monitoring = metaFixture().monitoring;

const texts: AdvancedTexts = {
  required: 'required',
  maxLength: (max) => `max ${String(max)}`,
  headerName: 'token',
  headerRefused: 'refused',
  headerDuplicate: 'duplicate',
  headerValue: 'control',
  tooManyHeaders: (max) => `at most ${String(max)} headers`,
  threshold: ({ min, max }) => `from ${String(min)} to ${String(max)}`,
};

function draft(overrides: Partial<AdvancedValues> = {}): AdvancedValues {
  return { ...advancedValues(null, monitoring), ...overrides };
}

function issuesOf(values: AdvancedValues, method: 'get' | 'head' | 'post' = 'get') {
  return advancedIssues(values, method, monitoring, texts).map((issue) => [issue.path.join('.'), issue.message]);
}

describe('advancedValues', () => {
  it('starts a new monitor from the defaults of meta with editable headers and body', () => {
    expect(advancedValues(null, { ...monitoring, default_keyword_match: 'absent', thresholds: { ...monitoring.thresholds, default_failure: 3, default_recovery: 4 } })).toEqual({
      keyword: '',
      keyword_match: 'absent',
      replace_headers: true,
      request_headers: [],
      replace_body: true,
      request_body: '',
      failure_threshold: '3',
      recovery_threshold: '4',
    });
  });

  it('keeps the stored headers and body of a monitor until they are replaced', () => {
    const monitor = monitorFixture({
      keyword: 'Welcome',
      keyword_match: 'absent',
      request_header_names: ['Authorization', 'X-Trace'],
      has_request_body: true,
      failure_threshold: 1,
      recovery_threshold: 5,
    });

    expect(advancedValues(monitor, monitoring)).toEqual({
      keyword: 'Welcome',
      keyword_match: 'absent',
      replace_headers: false,
      request_headers: [],
      replace_body: false,
      request_body: '',
      failure_threshold: '1',
      recovery_threshold: '5',
    });
  });

  it('lets a monitor without stored headers or body edit them at once', () => {
    const values = advancedValues(monitorFixture(), monitoring);

    expect(values.replace_headers).toBe(true);
    expect(values.replace_body).toBe(true);
    expect(values.keyword).toBe('');
  });
});

describe('advancedOpenAtStart', () => {
  it('stays closed for a new monitor and a monitor on the defaults', () => {
    expect(advancedOpenAtStart(null, monitoring)).toBe(false);
    expect(advancedOpenAtStart(monitorFixture(), monitoring)).toBe(false);
  });

  it('opens for a monitor with a keyword, headers, a body or a threshold off the default', () => {
    expect(advancedOpenAtStart(monitorFixture({ keyword: 'ok' }), monitoring)).toBe(true);
    expect(advancedOpenAtStart(monitorFixture({ request_header_names: ['Authorization'] }), monitoring)).toBe(true);
    expect(advancedOpenAtStart(monitorFixture({ has_request_body: true, method: 'post' }), monitoring)).toBe(true);
    expect(advancedOpenAtStart(monitorFixture({ failure_threshold: 1 }), monitoring)).toBe(true);
    expect(advancedOpenAtStart(monitorFixture({ recovery_threshold: 3 }), monitoring)).toBe(true);
  });
});

describe('replacingHeaderRows', () => {
  it('opens one row per stored name with an empty value', () => {
    expect(replacingHeaderRows(['Authorization', 'X-Trace'])).toEqual([
      { name: 'Authorization', value: '' },
      { name: 'X-Trace', value: '' },
    ]);
  });
});

describe('field visibility by method', () => {
  it('hides the keyword for HEAD only', () => {
    expect(keywordApplies('head')).toBe(false);
    expect((['get', 'post', 'put', 'patch'] as const).every(keywordApplies)).toBe(true);
  });

  it('shows the body for the body methods of meta', () => {
    expect(bodyApplies('post', monitoring)).toBe(true);
    expect(bodyApplies('patch', monitoring)).toBe(true);
    expect(bodyApplies('get', monitoring)).toBe(false);
    expect(bodyApplies('put', { ...monitoring, body_methods: ['post'] })).toBe(false);
  });
});

describe('knownKeywordMatches', () => {
  it('keeps the matches of meta the form can name', () => {
    expect(knownKeywordMatches(['present', 'absent', 'regex'])).toEqual(['present', 'absent']);
  });
});

describe('touchesAdvanced', () => {
  it('finds a field of the advanced section, a header row included', () => {
    expect(touchesAdvanced(['name', 'url'])).toBe(false);
    expect(touchesAdvanced(['name', 'failure_threshold'])).toBe(true);
    expect(touchesAdvanced(['request_headers.2.value'])).toBe(true);
    expect(touchesAdvanced(['keywords'])).toBe(false);
  });
});

describe('advancedIssues', () => {
  it('accepts the starting values of a new monitor', () => {
    expect(issuesOf(draft())).toEqual([]);
  });

  it('bounds the keyword by the length of meta, and ignores it for HEAD', () => {
    const keyword = 'k'.repeat(monitoring.lengths.keyword.max + 1);

    expect(issuesOf(draft({ keyword: 'k'.repeat(monitoring.lengths.keyword.max) }))).toEqual([]);
    expect(issuesOf(draft({ keyword }))).toEqual([['keyword', 'max 200']]);
    expect(issuesOf(draft({ keyword }), 'head')).toEqual([]);
  });

  it('asks a name and a value on every header row', () => {
    expect(issuesOf(draft({ request_headers: [{ name: ' ', value: '  ' }] }))).toEqual([
      ['request_headers.0.name', 'required'],
      ['request_headers.0.value', 'required'],
    ]);
  });

  it('takes a header name as an HTTP token within its length', () => {
    const long = 'X'.repeat(monitoring.lengths.request_header_name.max + 1);

    expect(issuesOf(draft({ request_headers: [{ name: "X-Api_Key.v2!#$%&'*+^`|~", value: 'a' }] }))).toEqual([]);
    expect(issuesOf(draft({ request_headers: [{ name: 'X Api', value: 'a' }] }))).toEqual([['request_headers.0.name', 'token']]);
    expect(issuesOf(draft({ request_headers: [{ name: 'X:Api', value: 'a' }] }))).toEqual([['request_headers.0.name', 'token']]);
    expect(issuesOf(draft({ request_headers: [{ name: 'Clé', value: 'a' }] }))).toEqual([['request_headers.0.name', 'token']]);
    expect(issuesOf(draft({ request_headers: [{ name: long, value: 'a' }] }))).toEqual([['request_headers.0.name', 'max 100']]);
  });

  it('refuses the names of meta in any case', () => {
    expect(issuesOf(draft({ request_headers: [{ name: 'Content-Length', value: '3' }] }))).toEqual([['request_headers.0.name', 'refused']]);
    expect(issuesOf(draft({ request_headers: [{ name: 'X-Ok', value: '1' }] }), 'get')).toEqual([]);
    expect(
      advancedIssues(draft({ request_headers: [{ name: 'x-blocked', value: '1' }] }), 'get', { ...monitoring, refused_header_names: ['X-Blocked'] }, texts).map((issue) => issue.message),
    ).toEqual(['refused']);
  });

  it('keeps header names unique ignoring case, the first one wins', () => {
    expect(
      issuesOf(
        draft({
          request_headers: [
            { name: 'Authorization', value: 'a' },
            { name: 'X-Trace', value: 'b' },
            { name: 'authorization', value: 'c' },
          ],
        }),
      ),
    ).toEqual([['request_headers.2.name', 'duplicate']]);
  });

  it('refuses a control character in a value, tab included, and bounds its length', () => {
    const long = 'v'.repeat(monitoring.lengths.request_header_value.max + 1);

    expect(issuesOf(draft({ request_headers: [{ name: 'X-Name', value: 'Bearer é ✓ 123' }] }))).toEqual([]);
    expect(issuesOf(draft({ request_headers: [{ name: 'X-Name', value: 'a\tb' }] }))).toEqual([['request_headers.0.value', 'control']]);
    expect(issuesOf(draft({ request_headers: [{ name: 'X-Name', value: 'a\u007fb' }] }))).toEqual([['request_headers.0.value', 'control']]);
    expect(issuesOf(draft({ request_headers: [{ name: 'X-Name', value: '  padded\t ' }] }))).toEqual([]);
    expect(issuesOf(draft({ request_headers: [{ name: 'X-Name', value: long }] }))).toEqual([['request_headers.0.value', 'max 1000']]);
  });

  it('bounds the number of header rows by meta', () => {
    const rows = Array.from({ length: monitoring.limits.max_request_headers + 1 }, (_, index) => ({ name: `X-Header-${String(index)}`, value: 'v' }));

    expect(issuesOf(draft({ request_headers: rows.slice(1) }))).toEqual([]);
    expect(issuesOf(draft({ request_headers: rows }))).toEqual([['request_headers', 'at most 20 headers']]);
  });

  it('does not check rows that are not being replaced', () => {
    expect(issuesOf(draft({ replace_headers: false, request_headers: [{ name: 'Host', value: '' }] }))).toEqual([]);
  });

  it('bounds the body by meta for a body method being replaced only', () => {
    const body = 'b'.repeat(monitoring.lengths.request_body.max + 1);

    expect(issuesOf(draft({ request_body: body.slice(1) }), 'post')).toEqual([]);
    expect(issuesOf(draft({ request_body: body }), 'post')).toEqual([['request_body', 'max 10000']]);
    expect(issuesOf(draft({ request_body: body }), 'get')).toEqual([]);
    expect(issuesOf(draft({ replace_body: false, request_body: body }), 'post')).toEqual([]);
  });

  it('counts characters, not code units', () => {
    const emoji = '😀'.repeat(monitoring.lengths.keyword.max);

    expect(issuesOf(draft({ keyword: emoji }))).toEqual([]);
  });

  it('takes whole thresholds within the bounds of meta', () => {
    expect(issuesOf(draft({ failure_threshold: '1', recovery_threshold: '10' }))).toEqual([]);
    expect(issuesOf(draft({ failure_threshold: '0', recovery_threshold: '11' }))).toEqual([
      ['failure_threshold', 'from 1 to 10'],
      ['recovery_threshold', 'from 1 to 10'],
    ]);
    expect(issuesOf(draft({ failure_threshold: '2.5', recovery_threshold: '' }))).toEqual([
      ['failure_threshold', 'from 1 to 10'],
      ['recovery_threshold', 'from 1 to 10'],
    ]);
  });
});

describe('methodNotes', () => {
  const post = monitorFixture({ method: 'post', has_request_body: true, keyword: 'ok' });

  it('says nothing while every field holding a value stays shown', () => {
    expect(methodNotes('post', { keyword: 'ok', request_body: '{}' }, post, monitoring)).toEqual([]);
    expect(methodNotes('get', { keyword: 'ok', request_body: '' }, null, monitoring)).toEqual([]);
  });

  it('warns that a stored or typed body goes away with a method without body', () => {
    expect(methodNotes('get', { keyword: '', request_body: '' }, post, monitoring)).toEqual(['drops_body']);
    expect(methodNotes('put', { keyword: '', request_body: '' }, post, monitoring)).toEqual([]);
    expect(methodNotes('get', { keyword: '', request_body: '{"a":1}' }, null, monitoring)).toEqual(['drops_body']);
    expect(methodNotes('get', { keyword: '', request_body: '' }, monitorFixture(), monitoring)).toEqual([]);
  });

  it('warns that the keyword goes away with HEAD', () => {
    expect(methodNotes('head', { keyword: 'ok', request_body: '' }, null, monitoring)).toEqual(['drops_keyword']);
    expect(methodNotes('head', { keyword: '  ', request_body: '' }, null, monitoring)).toEqual([]);
    expect(methodNotes('head', { keyword: 'ok', request_body: '' }, post, monitoring)).toEqual(['drops_body', 'drops_keyword']);
  });
});

describe('advancedCreateBody', () => {
  it('sends the thresholds and nothing else from the starting values', () => {
    expect(advancedCreateBody(draft(), 'get', monitoring)).toEqual({ failure_threshold: 2, recovery_threshold: 2 });
  });

  it('sends the trimmed keyword with its match, headers trimmed and the body as typed', () => {
    const values = draft({
      keyword: '  Welcome ',
      keyword_match: 'absent',
      request_headers: [
        { name: ' Authorization ', value: ' Bearer abc ' },
        { name: 'Content-Type', value: 'application/json' },
      ],
      request_body: ' {"ping": true}\n',
      failure_threshold: '1',
      recovery_threshold: '3',
    });

    expect(advancedCreateBody(values, 'post', monitoring)).toEqual({
      keyword: 'Welcome',
      keyword_match: 'absent',
      request_headers: [
        { name: 'Authorization', value: 'Bearer abc' },
        { name: 'Content-Type', value: 'application/json' },
      ],
      request_body: ' {"ping": true}\n',
      failure_threshold: 1,
      recovery_threshold: 3,
    });
  });

  it('leaves out the fields the method hides', () => {
    const values = draft({ keyword: 'Welcome', request_body: '{}' });

    expect(advancedCreateBody(values, 'get', monitoring)).toEqual({ keyword: 'Welcome', keyword_match: 'present', failure_threshold: 2, recovery_threshold: 2 });
    expect(advancedCreateBody(values, 'head', monitoring)).toEqual({ failure_threshold: 2, recovery_threshold: 2 });
  });
});

describe('advancedUpdateBody', () => {
  const stored = monitorFixture({
    method: 'post',
    keyword: 'Welcome',
    keyword_match: 'present',
    request_header_names: ['Authorization'],
    has_request_body: true,
  });

  it('sends nothing while nothing changes, stored headers and body included', () => {
    expect(advancedUpdateBody(stored, advancedValues(stored, monitoring), 'post', monitoring)).toEqual({});
    expect(advancedUpdateBody(monitorFixture(), advancedValues(monitorFixture(), monitoring), 'get', monitoring)).toEqual({});
  });

  it('sends a changed keyword, a cleared keyword as null and the match only with a keyword', () => {
    const values = advancedValues(stored, monitoring);

    expect(advancedUpdateBody(stored, { ...values, keyword: 'Hello' }, 'post', monitoring)).toEqual({ keyword: 'Hello' });
    expect(advancedUpdateBody(stored, { ...values, keyword: ' ', keyword_match: 'absent' }, 'post', monitoring)).toEqual({ keyword: null });
    expect(advancedUpdateBody(stored, { ...values, keyword_match: 'absent' }, 'post', monitoring)).toEqual({ keyword_match: 'absent' });
  });

  it('sends the whole list of replaced headers, and an empty list to clear them', () => {
    const replaced = { ...advancedValues(stored, monitoring), replace_headers: true };
    const rows = [
      { name: 'Authorization', value: 'Bearer new' },
      { name: 'X-Trace', value: '1' },
    ];

    expect(advancedUpdateBody(stored, { ...replaced, request_headers: rows }, 'post', monitoring)).toEqual({ request_headers: rows });
    expect(advancedUpdateBody(stored, { ...replaced, request_headers: [] }, 'post', monitoring)).toEqual({ request_headers: [] });
  });

  it('sends headers added to a monitor that had none, and nothing for no rows', () => {
    const plain = monitorFixture();
    const values = advancedValues(plain, monitoring);

    expect(advancedUpdateBody(plain, { ...values, request_headers: [{ name: 'X-Trace', value: '1' }] }, 'get', monitoring)).toEqual({ request_headers: [{ name: 'X-Trace', value: '1' }] });
    expect(advancedUpdateBody(plain, { ...values, request_headers: [] }, 'get', monitoring)).toEqual({});
  });

  it('keeps the stored body without Replace, sends the new one or null with it', () => {
    const replaced = { ...advancedValues(stored, monitoring), replace_body: true };

    expect(advancedUpdateBody(stored, { ...advancedValues(stored, monitoring), request_body: 'ignored' }, 'post', monitoring)).toEqual({});
    expect(advancedUpdateBody(stored, { ...replaced, request_body: '{"a":1}' }, 'post', monitoring)).toEqual({ request_body: '{"a":1}' });
    expect(advancedUpdateBody(stored, { ...replaced, request_body: '' }, 'post', monitoring)).toEqual({ request_body: null });
  });

  it('sends a body to a monitor that had none, and nothing for an empty one', () => {
    const plain = monitorFixture({ method: 'put' });
    const values = advancedValues(plain, monitoring);

    expect(advancedUpdateBody(plain, { ...values, request_body: 'x' }, 'put', monitoring)).toEqual({ request_body: 'x' });
    expect(advancedUpdateBody(plain, values, 'put', monitoring)).toEqual({});
  });

  it('sends null for the fields a new method hides', () => {
    const values = advancedValues(stored, monitoring);

    expect(advancedUpdateBody(stored, values, 'get', monitoring)).toEqual({ request_body: null });
    expect(advancedUpdateBody(stored, values, 'head', monitoring)).toEqual({ keyword: null, request_body: null });
    expect(advancedUpdateBody(monitorFixture(), { ...advancedValues(monitorFixture(), monitoring), request_body: 'typed' }, 'get', monitoring)).toEqual({});
  });

  it('sends a changed threshold only', () => {
    const values = advancedValues(stored, monitoring);

    expect(advancedUpdateBody(stored, { ...values, failure_threshold: '1' }, 'post', monitoring)).toEqual({ failure_threshold: 1 });
    expect(advancedUpdateBody(stored, { ...values, recovery_threshold: '4' }, 'post', monitoring)).toEqual({ recovery_threshold: 4 });
  });
});
