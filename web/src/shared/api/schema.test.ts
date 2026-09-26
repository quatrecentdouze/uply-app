import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import openapiTS, { astToString, COMMENT_HEADER } from 'openapi-typescript';
import { describe, expect, it } from 'vitest';
import { projectRoot, readProjectFile } from '@/shared/testing/sourceFiles';

describe('schema.d.ts', () => {
  it('matches what openapi-typescript generates from api/openapi.json', async () => {
    const contract = pathToFileURL(join(projectRoot, '..', 'api', 'openapi.json'));

    const generated = `${COMMENT_HEADER}${astToString(await openapiTS(contract))}`;

    expect(readProjectFile('src/shared/api/schema.d.ts') === generated, 'run npm run api:types').toBe(true);
  });
});
