import type { FieldValues, Path, UseFormSetError } from 'react-hook-form';
import { type ApiFailure, errorTextKey, fieldErrors } from '@/shared/api/errors';
import type { TextKey } from '@/shared/i18n/textKey';

export const serverErrorField = 'root.server';

export function withFieldRoots(failure: ApiFailure): ApiFailure {
  if (failure.kind !== 'validation') {
    return failure;
  }
  const fields: Record<string, readonly string[]> = {};
  for (const [field, messages] of Object.entries(failure.fields)) {
    const root = field.split('.')[0] ?? field;
    fields[root] = [...(fields[root] ?? []), ...messages];
  }
  return { ...failure, fields };
}

export function reportFailure<Values extends FieldValues>(
  setError: UseFormSetError<Values>,
  failure: ApiFailure,
  fields: readonly Path<Values>[],
  translate: (key: TextKey<'errors'>) => string,
): void {
  const unmatched: string[] = [];
  for (const [field, message] of fieldErrors(failure)) {
    const known = fields.find((candidate) => candidate === field);
    if (known === undefined) {
      unmatched.push(message);
    } else {
      setError(known, { type: 'server', message });
    }
  }
  if (failure.kind !== 'validation') {
    setError(serverErrorField, { type: 'server', message: translate(errorTextKey(failure)) });
    return;
  }
  const first = unmatched[0];
  if (first !== undefined) {
    setError(serverErrorField, { type: 'server', message: first });
  }
}
