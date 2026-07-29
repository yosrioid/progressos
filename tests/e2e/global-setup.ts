import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const flag = join(process.cwd(), '.playwright-running');

export default async function globalSetup() {
  // Signal to the Laravel app that we're running under Playwright so it can
  // relax rate-limiters (login, register, etc.) that would otherwise block
  // parallel test workers within a single minute.
  writeFileSync(flag, String(Date.now()));

  execFileSync('php', ['artisan', 'migrate', '--force'], { stdio: 'inherit' });
  execFileSync('php', ['artisan', 'db:seed', '--class=E2eSeeder', '--force'], { stdio: 'inherit' });

  return async () => {
    if (existsSync(flag)) rmSync(flag);
  };
}
