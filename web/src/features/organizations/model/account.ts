import { z } from 'zod';
import type { AccountLengths } from '@/shared/api/meta';
import type { Me } from '@/shared/api/resources';
import type { BaseValidationTexts } from '@/shared/i18n/useValidationTexts';

export interface ProfileValues {
  name: string;
  email: string;
  current_password: string;
}

function emailChanged(me: Pick<Me, 'email'>, email: string): boolean {
  return email.trim().toLowerCase() !== me.email.toLowerCase();
}

export function profileSchema(texts: BaseValidationTexts, lengths: AccountLengths | null, me: Pick<Me, 'email'>) {
  const name = z.string().trim().min(1, texts.required);
  return z
    .object({
      name: lengths === null ? name : name.max(lengths.user_name.max, texts.length({ min: lengths.user_name.min, max: lengths.user_name.max })),
      email: z.string().trim().min(1, texts.required).pipe(z.email(texts.email)),
      current_password: z.string(),
    })
    .refine((values) => !emailChanged(me, values.email) || values.current_password !== '', { path: ['current_password'], message: texts.required });
}

export function profileBody(me: Pick<Me, 'name' | 'email'>, values: ProfileValues): { name?: string; email?: string; current_password?: string } {
  const name = values.name.trim();
  const email = values.email.trim();
  return {
    ...(name === me.name ? {} : { name }),
    ...(emailChanged(me, email) ? { email, current_password: values.current_password } : {}),
  };
}

export function needsCurrentPassword(me: Pick<Me, 'email'>, email: string): boolean {
  return emailChanged(me, email);
}

export function passwordSchema(texts: BaseValidationTexts, lengths: AccountLengths | null) {
  const password = z.string().min(1, texts.required);
  return z
    .object({
      current_password: z.string().min(1, texts.required),
      password: lengths === null ? password : password.min(lengths.password.min, texts.length(lengths.password)).max(lengths.password.max, texts.length(lengths.password)),
      password_confirmation: z.string().min(1, texts.required),
    })
    .refine((values) => values.password === values.password_confirmation, { path: ['password_confirmation'], message: texts.mismatch });
}

export type PasswordValues = z.infer<ReturnType<typeof passwordSchema>>;

const codeLength = 6;
const codePattern = new RegExp(`^\\d{${String(codeLength)}}$`);

export function confirmationCodeSchema(texts: { code: string }) {
  return z.object({ code: z.string().trim().regex(codePattern, texts.code) });
}

export type ConfirmationCodeValues = z.infer<ReturnType<typeof confirmationCodeSchema>>;

export type TwoFactorStep = { kind: 'off' } | { kind: 'password' } | { kind: 'scan' } | { kind: 'codes'; codes: readonly string[] } | { kind: 'on' };

export function restingStep(me: Pick<Me, 'two_factor_enabled'>): TwoFactorStep {
  return me.two_factor_enabled ? { kind: 'on' } : { kind: 'off' };
}

export function qrImageSource(svg: string): string {
  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
}
