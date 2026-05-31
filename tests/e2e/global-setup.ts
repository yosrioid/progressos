import { execFileSync } from 'node:child_process';

export default async function globalSetup() {
  execFileSync('php', ['artisan', 'migrate', '--force'], { stdio: 'inherit' });
  execFileSync('php', ['artisan', 'db:seed', '--class=E2eSeeder', '--force'], { stdio: 'inherit' });
}
