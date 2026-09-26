import { z } from 'zod';
import type { TextKey } from '@/shared/i18n/textKey';

export const domainErrorCodes = [
  'already_member',
  'invitation_email_mismatch',
  'invitation_expired',
  'invitation_limit_reached',
  'invitation_pending',
  'last_owner',
  'monitor_limit_reached',
  'organization_limit_reached',
  'role_not_allowed',
  'slug_taken',
  'sole_owner',
  'status_page_limit_reached',
  'test_limit_reached',
] as const;

export type DomainErrorCode = (typeof domainErrorCodes)[number];

export type ApiFailure =
  | { kind: 'validation'; message: string; fields: Readonly<Record<string, readonly string[]>> }
  | { kind: 'domain'; status: number; code: DomainErrorCode | null; message: string }
  | { kind: 'unauthenticated' }
  | { kind: 'forbidden' }
  | { kind: 'not_found' }
  | { kind: 'session_expired' }
  | { kind: 'rate_limited'; retryAfterSeconds: number | null }
  | { kind: 'server'; status: number }
  | { kind: 'network' };

export class ApiError extends Error {
  readonly failure: ApiFailure;

  constructor(failure: ApiFailure) {
    super(failure.kind);
    this.name = 'ApiError';
    this.failure = failure;
  }
}

const validationBody = z.object({
  message: z.string(),
  errors: z.record(z.string(), z.array(z.string())),
});

const domainBody = z.object({
  message: z.string(),
  code: z.string(),
});

function isDomainErrorCode(code: string): code is DomainErrorCode {
  return (domainErrorCodes as readonly string[]).includes(code);
}

function retryAfter(headers: Headers): number | null {
  const value = Number.parseInt(headers.get('Retry-After') ?? '', 10);
  return Number.isFinite(value) && value >= 0 ? value : null;
}

const httpStatus = {
  unauthenticated: 401,
  forbidden: 403,
  notFound: 404,
  sessionExpired: 419,
  unprocessable: 422,
  tooManyRequests: 429,
} as const;

export function failureFromResponse(status: number, body: unknown, headers: Headers): ApiFailure {
  const domain = domainBody.safeParse(body);
  if (domain.success) {
    return {
      kind: 'domain',
      status,
      code: isDomainErrorCode(domain.data.code) ? domain.data.code : null,
      message: domain.data.message,
    };
  }
  if (status === httpStatus.unprocessable) {
    const validation = validationBody.safeParse(body);
    if (validation.success) {
      return { kind: 'validation', message: validation.data.message, fields: validation.data.errors };
    }
  }
  switch (status) {
    case httpStatus.unauthenticated:
      return { kind: 'unauthenticated' };
    case httpStatus.forbidden:
      return { kind: 'forbidden' };
    case httpStatus.notFound:
      return { kind: 'not_found' };
    case httpStatus.sessionExpired:
      return { kind: 'session_expired' };
    case httpStatus.tooManyRequests:
      return { kind: 'rate_limited', retryAfterSeconds: retryAfter(headers) };
    default:
      return { kind: 'server', status };
  }
}

export function failureOf(error: unknown): ApiFailure {
  return error instanceof ApiError ? error.failure : { kind: 'network' };
}

export function isFailure(error: unknown, kind: ApiFailure['kind']): boolean {
  return failureOf(error).kind === kind;
}

const domainTextKeys: Record<DomainErrorCode, TextKey<'errors'>> = {
  already_member: 'errors:codes.already_member',
  invitation_email_mismatch: 'errors:codes.invitation_email_mismatch',
  invitation_expired: 'errors:codes.invitation_expired',
  invitation_limit_reached: 'errors:codes.invitation_limit_reached',
  invitation_pending: 'errors:codes.invitation_pending',
  last_owner: 'errors:codes.last_owner',
  monitor_limit_reached: 'errors:codes.monitor_limit_reached',
  organization_limit_reached: 'errors:codes.organization_limit_reached',
  role_not_allowed: 'errors:codes.role_not_allowed',
  slug_taken: 'errors:codes.slug_taken',
  sole_owner: 'errors:codes.sole_owner',
  status_page_limit_reached: 'errors:codes.status_page_limit_reached',
  test_limit_reached: 'errors:codes.test_limit_reached',
};

export function errorTextKey(failure: ApiFailure): TextKey<'errors'> {
  switch (failure.kind) {
    case 'domain':
      return failure.code === null ? 'errors:generic' : domainTextKeys[failure.code];
    case 'validation':
      return 'errors:validation';
    case 'unauthenticated':
      return 'errors:unauthenticated';
    case 'forbidden':
      return 'errors:forbidden';
    case 'not_found':
      return 'errors:not_found';
    case 'session_expired':
      return 'errors:session_expired';
    case 'rate_limited':
      return 'errors:rate_limited';
    case 'server':
      return 'errors:server';
    case 'network':
      return 'errors:network';
  }
}

export function fieldErrors(failure: ApiFailure): ReadonlyArray<readonly [string, string]> {
  if (failure.kind !== 'validation') {
    return [];
  }
  return Object.entries(failure.fields).flatMap(([field, messages]) => {
    const first = messages[0];
    return first === undefined ? [] : [[field, first] as const];
  });
}
