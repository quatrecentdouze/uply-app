import { describe, expect, it } from 'vitest';
import {
  availableMonitors,
  monitorName,
  newStatusPageSchema,
  slugFrom,
  type StatusPageFormTexts,
  statusPageFormValues,
  statusPageSchema,
  updateStatusPageBody,
} from '@/features/status-pages/model/statusPageForm';
import type { StatusPage } from '@/shared/api/resources';
import { metaFixture } from '@/shared/testing/fixtures';

const meta = metaFixture().status_pages;

const texts: StatusPageFormTexts = {
  required: 'required',
  email: 'email',
  length: () => 'length',
  mismatch: 'mismatch',
  maxLength: (max) => `max ${String(max)}`,
  slug: ({ min, max }) => `slug ${String(min)}-${String(max)}`,
  tooManyMonitors: (max) => `too many ${String(max)}`,
};

const page: StatusPage = {
  id: 'page-1',
  slug: 'acme-status',
  title: 'Acme status',
  description: null,
  is_published: false,
  monitors: [
    { monitor_id: 'mon-2', display_name: 'Website', position: 1 },
    { monitor_id: 'mon-1', display_name: 'API', position: 0 },
  ],
  created_at: null,
  updated_at: null,
};

function issues(values: Record<string, unknown>) {
  const result = statusPageSchema(texts, meta).safeParse({ ...statusPageFormValues(page), ...values });
  return Object.fromEntries((result.error?.issues ?? []).map((issue) => [issue.path.join('.'), issue.message]));
}

describe('slugFrom', () => {
  it('turns a title into an address', () => {
    expect(slugFrom('  Acme Café — Status!  ', 48)).toBe('acme-cafe-status');
    expect(slugFrom('a'.repeat(30) + ' ' + 'b'.repeat(30), 48)).toBe(`${'a'.repeat(30)}-${'b'.repeat(17)}`);
    expect(slugFrom('x'.repeat(47) + ' y', 48)).toBe('x'.repeat(47));
  });
});

describe('statusPageSchema', () => {
  it('accepts the page as it is', () => {
    expect(issues({})).toEqual({});
  });

  it('checks the slug pattern and length from meta', () => {
    expect(issues({ slug: 'ab' })).toEqual({ slug: 'slug 3-48' });
    expect(issues({ slug: 'Acme Status' })).toEqual({ slug: 'slug 3-48' });
  });

  it('bounds the title, the description, the display names and the number of monitors', () => {
    expect(issues({ title: '', description: 'x'.repeat(501) })).toEqual({ title: 'required', description: 'max 500' });
    expect(issues({ monitors: [{ monitor_id: 'mon-1', display_name: 'x'.repeat(81) }] })).toEqual({ 'monitors.0.display_name': 'max 80' });
    expect(issues({ monitors: Array.from({ length: 51 }, (_, index) => ({ monitor_id: `m${String(index)}`, display_name: '' })) })).toEqual({ monitors: 'too many 50' });
  });

  it('needs a title and a slug to create a page', () => {
    expect(newStatusPageSchema(texts, meta).safeParse({ title: 'Acme', slug: 'acme' }).success).toBe(true);
    expect(newStatusPageSchema(texts, meta).safeParse({ title: 'Acme', slug: '' }).success).toBe(false);
  });
});

describe('statusPageFormValues', () => {
  it('orders the monitors by position', () => {
    expect(statusPageFormValues(page).monitors.map((monitor) => monitor.monitor_id)).toEqual(['mon-1', 'mon-2']);
  });
});

describe('updateStatusPageBody', () => {
  it('sends nothing when nothing changed', () => {
    expect(updateStatusPageBody(page, statusPageFormValues(page))).toEqual({});
  });

  it('sends the whole monitor list in its new order, an empty display name falling back to the monitor name', () => {
    const values = statusPageFormValues(page);

    expect(
      updateStatusPageBody(page, {
        ...values,
        is_published: true,
        description: '  ',
        monitors: [
          { monitor_id: 'mon-2', display_name: 'Website' },
          { monitor_id: 'mon-1', display_name: ' ' },
        ],
      }),
    ).toEqual({
      is_published: true,
      monitors: [{ monitor_id: 'mon-2', display_name: 'Website' }, { monitor_id: 'mon-1' }],
    });
  });

  it('clears the description with null', () => {
    expect(updateStatusPageBody({ ...page, description: 'Old' }, { ...statusPageFormValues({ ...page, description: 'Old' }), description: '' })).toEqual({ description: null });
  });
});

describe('monitor choices', () => {
  const options = [
    { id: 'mon-1', name: 'Public API' },
    { id: 'mon-3', name: 'Search' },
  ];

  it('offers the monitors not on the page yet', () => {
    expect(availableMonitors(options, [{ monitor_id: 'mon-1', display_name: '' }])).toEqual([{ id: 'mon-3', name: 'Search' }]);
  });

  it('finds the name of a monitor', () => {
    expect(monitorName(options, 'mon-3')).toBe('Search');
    expect(monitorName(options, 'gone')).toBeNull();
  });
});
