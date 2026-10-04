import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { z } from 'zod';
import { projectRoot } from '@/shared/testing/sourceFiles';

const parameter = z.object({ name: z.string(), in: z.string() });
const document = z.object({
  paths: z.record(z.string(), z.record(z.string(), z.object({ parameters: z.array(parameter).optional() }).loose())),
  components: z.object({ schemas: z.record(z.string(), z.object({ properties: z.record(z.string(), z.object({ enum: z.array(z.unknown()).optional() }).loose()).optional() }).loose()) }),
});

const contract = document.parse(JSON.parse(readFileSync(join(projectRoot, '..', 'api', 'openapi.json'), 'utf8')));

function queryParameters(path: string, method: string): string[] {
  return (contract.paths[path]?.[method]?.parameters ?? []).filter((candidate) => candidate.in === 'query').map((candidate) => candidate.name);
}

describe('the overlay of paths.ts', () => {
  it('is needed while both deletes document the password in the query, the API reads it from the body', () => {
    expect(queryParameters('/v1/me', 'delete')).toContain('password');
    expect(queryParameters('/v1/organizations/{organization}', 'delete')).toContain('password');
  });

  it('is needed while interval_seconds lists text values, the API wants an integer', () => {
    for (const schema of ['StoreMonitorRequest', 'UpdateMonitorRequest']) {
      expect(contract.components.schemas[schema]?.properties?.interval_seconds?.enum?.every((value) => typeof value === 'string')).toBe(true);
    }
  });
});
