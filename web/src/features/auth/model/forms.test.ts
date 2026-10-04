import { describe, expect, it } from 'vitest';
import { metaFixture } from '@/shared/testing/fixtures';
import { loginSchema, registerSchema, resetLinkOf, resetPasswordSchema, twoFactorBody, twoFactorSchema, type ValidationTexts } from '@/features/auth/model/forms';

const texts: ValidationTexts & { code: string } = {
  required: 'required',
  email: 'email',
  length: ({ min, max }) => `length ${String(min)}-${String(max)}`,
  mismatch: 'mismatch',
  code: 'code',
};

const lengths = metaFixture().organizations.lengths;

function messages(result: { success: boolean; error?: { issues: { path: PropertyKey[]; message: string }[] } }) {
  return Object.fromEntries((result.error?.issues ?? []).map((issue) => [issue.path.join('.'), issue.message]));
}

describe('loginSchema', () => {
  it('asks for a valid email and a password', () => {
    expect(messages(loginSchema(texts).safeParse({ email: 'nope', password: '' }))).toEqual({ email: 'email', password: 'required' });
    expect(loginSchema(texts).safeParse({ email: 'demo@uply.test', password: 'x' }).success).toBe(true);
  });
});

describe('registerSchema', () => {
  const valid = { name: 'Demo', email: 'demo@uply.test', password: 'long enough pw', password_confirmation: 'long enough pw' };

  it('accepts a valid account', () => {
    expect(registerSchema(texts, lengths).safeParse(valid).success).toBe(true);
  });

  it('checks the password length from meta and the confirmation', () => {
    expect(messages(registerSchema(texts, lengths).safeParse({ ...valid, password: 'short', password_confirmation: 'other' }))).toEqual({
      password: 'length 12-255',
      password_confirmation: 'mismatch',
    });
  });

  it('leaves the length to the server when meta has none', () => {
    expect(registerSchema(texts, null).safeParse({ ...valid, password: 'short', password_confirmation: 'short' }).success).toBe(true);
  });
});

describe('resetPasswordSchema', () => {
  it('needs matching passwords', () => {
    expect(messages(resetPasswordSchema(texts, lengths).safeParse({ email: 'demo@uply.test', password: 'long enough pw', password_confirmation: 'x' }))).toEqual({
      password_confirmation: 'mismatch',
    });
  });
});

describe('twoFactor', () => {
  it('needs six digits in code mode and any text in recovery mode', () => {
    expect(messages(twoFactorSchema(texts, 'code').safeParse({ answer: '12345' }))).toEqual({ answer: 'code' });
    expect(twoFactorSchema(texts, 'code').safeParse({ answer: ' 123456 ' }).success).toBe(true);
    expect(twoFactorSchema(texts, 'recovery').safeParse({ answer: 'abcd-efgh' }).success).toBe(true);
  });

  it('sends the field of the chosen mode', () => {
    expect(twoFactorBody('code', ' 123456 ')).toEqual({ code: '123456' });
    expect(twoFactorBody('recovery', 'abcd-efgh')).toEqual({ recovery_code: 'abcd-efgh' });
  });
});

describe('resetLinkOf', () => {
  it('needs both the token and the email of the mail link', () => {
    expect(resetLinkOf({ token: 'abc', email: 'demo@uply.test' })).toEqual({ token: 'abc', email: 'demo@uply.test' });
    expect(resetLinkOf({ token: 'abc' })).toBeNull();
    expect(resetLinkOf({ token: ' ', email: 'demo@uply.test' })).toBeNull();
  });
});
