import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { cwd } from 'node:process';

export interface SourceFile {
  path: string;
  content: string;
}

export const projectRoot = cwd();

const sourceExtensions = ['.ts', '.tsx'];

function walk(directory: string): string[] {
  return readdirSync(directory).flatMap((entry) => {
    const full = join(directory, entry);
    return statSync(full).isDirectory() ? walk(full) : [full];
  });
}

export function sourceFiles(folder: string): SourceFile[] {
  return walk(join(projectRoot, folder))
    .filter((file) => sourceExtensions.some((extension) => file.endsWith(extension)))
    .map((file) => ({
      path: relative(projectRoot, file).split(sep).join('/'),
      content: readFileSync(file, 'utf8'),
    }));
}

export function readProjectFile(path: string): string {
  return readFileSync(join(projectRoot, path), 'utf8');
}
