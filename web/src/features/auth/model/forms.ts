import { z } from 'zod';
import type { AccountLengths, Bounds } from '@/shared/api/meta';
import type { BaseValidationTexts } from '@/shared/i18n/useValidationTexts';

export type ValidationTexts = BaseValidationTexts;

export interface CodeTexts {
  required: string;
  code: string;
}

const authCodeLength = 6;
const authCodePattern = new RegExp(`^\\d{${String(authCodeLength)}}$`);

function requiredText(texts: { required: string }) {
  return z.string().trim().min(1, texts.required);
}

function emailField(texts: ValidationTexts) {
  return requiredText(texts).pipe(z.email(texts.email));
}

function bounded(texts: ValidationTexts, bounds: Bounds | undefined) {
  const field = z.string().min(1, texts.required);
  return bounds === undefined ? field : field.min(bounds.min, texts.length(bounds)).max(bounds.max, texts.length(bounds));
}

export function loginSchema(texts: ValidationTexts) {
  return z.object({
    email: emailField(texts),
    password: z.string().min(1, texts.required),
  });
}

export type LoginValues = z.infer<ReturnType<typeof loginSchema>>;

export function registerSchema(texts: ValidationTexts, lengths: AccountLengths | null) {
  return z
    .object({
      name: requiredText(texts).pipe(bounded(texts, lengths?.user_name)),
      email: emailField(texts),
      password: bounded(texts, lengths?.password),
      password_confirmation: z.string().min(1, texts.required),
    })
    .refine((values) => values.password === values.password_confirmation, { path: ['password_confirmation'], message: texts.mismatch });
}

export type RegisterValues = z.infer<ReturnType<typeof registerSchema>>;

export function forgotPasswordSchema(texts: ValidationTexts) {
  return z.object({ email: emailField(texts) });
}

export type ForgotPasswordValues = z.infer<ReturnType<typeof forgotPasswordSchema>>;

export function resetPasswordSchema(texts: ValidationTexts, lengths: AccountLengths | null) {
  return z
    .object({
      email: emailField(texts),
      password: bounded(texts, lengths?.password),
      password_confirmation: z.string().min(1, texts.required),
    })
    .refine((values) => values.password === values.password_confirmation, { path: ['password_confirmation'], message: texts.mismatch });
}

export type ResetPasswordValues = z.infer<ReturnType<typeof resetPasswordSchema>>;

export type TwoFactorMode = 'code' | 'recovery';

export function twoFactorSchema(texts: CodeTexts, mode: TwoFactorMode) {
  return z.object({
    answer: mode === 'code' ? z.string().trim().regex(authCodePattern, texts.code) : requiredText(texts),
  });
}

export type TwoFactorValues = z.infer<ReturnType<typeof twoFactorSchema>>;

export function twoFactorBody(mode: TwoFactorMode, answer: string): { code: string } | { recovery_code: string } {
  return mode === 'code' ? { code: answer.trim() } : { recovery_code: answer.trim() };
}

export interface ResetLink {
  token: string;
  email: string;
}

export function resetLinkOf(search: { token?: string | undefined; email?: string | undefined }): ResetLink | null {
  const token = search.token?.trim() ?? '';
  const email = search.email?.trim() ?? '';
  return token === '' || email === '' ? null : { token, email };
}
