import { describe, expect, it } from 'vitest';
import {
  advancedOpenAtStart,
  advancedValues,
  bodyApplies,
  keywordApplies,
  knownKeywordMatches,
  replacingHeaderRows,
  touchesAdvanced,
} from '@/features/monitors/model/advancedForm';
import { metaFixture, monitorFixture } from '@/shared/testing/fixtures';

const monitoring = metaFixture().monitoring;

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
