import { describe, expect, it } from 'vitest';
import { createI18n } from '@/shared/i18n/i18n';
import { domainErrorCodes, errorTextKey, failureFromResponse, fieldErrors } from '@/shared/api/errors';

const noHeaders = new Headers();

describe('failureFromResponse', () => {
  it('reads a domain error with a known code', () => {
    expect(failureFromResponse(409, { message: 'Last owner.', code: 'last_owner' }, noHeaders)).toEqual({
      kind: 'domain',
      status: 409,
      code: 'last_owner',
      message: 'Last owner.',
    });
  });

  it('keeps an unknown code as a generic domain error', () => {
    expect(failureFromResponse(409, { message: 'New.', code: 'brand_new' }, noHeaders)).toMatchObject({ kind: 'domain', code: null });
  });

  it('reads the field errors of a 422', () => {
    const failure = failureFromResponse(422, { message: 'Invalid.', errors: { email: ['The email is taken.', 'Second.'] } }, noHeaders);

    expect(fieldErrors(failure)).toEqual([['email', 'The email is taken.']]);
  });

  it('reads Retry-After on a 429', () => {
    expect(failureFromResponse(429, { message: 'Too many.' }, new Headers({ 'Retry-After': '12' }))).toEqual({
      kind: 'rate_limited',
      retryAfterSeconds: 12,
    });
  });

  it.each([
    [401, 'unauthenticated'],
    [403, 'forbidden'],
    [404, 'not_found'],
    [419, 'session_expired'],
    [423, 'password_confirmation'],
    [500, 'server'],
  ])('maps %i to %s', (status, kind) => {
    expect(failureFromResponse(status, { message: 'x' }, noHeaders).kind).toBe(kind);
  });
});

describe('errorTextKey', () => {
  const i18n = createI18n();

  it('shows the text of a domain error code', () => {
    const key = errorTextKey({ kind: 'domain', status: 409, code: 'invitation_expired', message: 'Expired.' });

    expect(i18n.t(key)).toBe('This invitation has expired. Ask for a new one.');
  });

  it('falls back to the generic text for an unknown code', () => {
    expect(i18n.t(errorTextKey({ kind: 'domain', status: 409, code: null, message: 'x' }))).toBe('Something went wrong. Please try again.');
  });

  it('has a text for every known code', () => {
    for (const code of domainErrorCodes) {
      const key = errorTextKey({ kind: 'domain', status: 409, code, message: 'x' });
      expect(i18n.exists(key)).toBe(true);
    }
  });
});
