import { describe, expect, it } from 'vitest';
import { confirmationCodeSchema, needsCurrentPassword, passwordSchema, profileBody, profileSchema, qrImageSource, restingStep } from '@/features/organizations/model/account';
import { meFixture, metaFixture } from '@/shared/testing/fixtures';

const texts = { required: 'required', email: 'email', length: ({ min, max }: { min: number; max: number }) => `length ${String(min)}-${String(max)}`, mismatch: 'mismatch' };
const lengths = metaFixture().organizations.lengths;
const me = meFixture({ name: 'Demo User', email: 'demo@uply.test' });

function messages(result: { error?: { issues: { path: PropertyKey[]; message: string }[] } }) {
  return Object.fromEntries((result.error?.issues ?? []).map((issue) => [issue.path.join('.'), issue.message]));
}

describe('profile', () => {
  it('asks for the current password only when the email changes', () => {
    const schema = profileSchema(texts, lengths, me);

    expect(messages(schema.safeParse({ name: 'Renamed', email: 'demo@uply.test', current_password: '' }))).toEqual({});
    expect(messages(schema.safeParse({ name: 'Renamed', email: 'new@uply.test', current_password: '' }))).toEqual({ current_password: 'required' });
    expect(needsCurrentPassword(me, ' DEMO@uply.test ')).toBe(false);
  });

  it('sends only what changed', () => {
    expect(profileBody(me, { name: 'Demo User', email: 'demo@uply.test', current_password: '' })).toEqual({});
    expect(profileBody(me, { name: ' Ada ', email: 'ada@uply.test', current_password: 'secret' })).toEqual({ name: 'Ada', email: 'ada@uply.test', current_password: 'secret' });
  });
});

describe('passwordSchema', () => {
  it('bounds the new password from meta and checks the confirmation', () => {
    expect(messages(passwordSchema(texts, lengths).safeParse({ current_password: 'x', password: 'short', password_confirmation: 'other' }))).toEqual({
      password: 'length 12-255',
      password_confirmation: 'mismatch',
    });
  });
});

describe('two factor', () => {
  it('needs six digits to confirm the setup', () => {
    expect(confirmationCodeSchema({ code: 'code' }).safeParse({ code: ' 123456 ' }).success).toBe(true);
    expect(confirmationCodeSchema({ code: 'code' }).safeParse({ code: '12345a' }).success).toBe(false);
  });

  it('rests on or off from the session', () => {
    expect(restingStep({ two_factor_enabled: true })).toEqual({ kind: 'on' });
    expect(restingStep({ two_factor_enabled: false })).toEqual({ kind: 'off' });
  });

  it('turns the QR code into an image address', () => {
    expect(qrImageSource('<svg a="1"></svg>')).toBe('data:image/svg+xml;charset=utf-8,%3Csvg%20a%3D%221%22%3E%3C%2Fsvg%3E');
  });
});
