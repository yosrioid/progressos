import { expect, test, type Page } from '@playwright/test';

const today = new Date().toISOString().slice(0, 10);

async function login(page: Page) {
  await page.goto('/login');
  await page.locator('input[type="email"]').fill('e2e@example.com');
  await page.locator('input[type="password"]').fill('password');
  await page.getByRole('button', { name: /log in/i }).click();
  await expect(page).toHaveURL(/\/dashboard/);
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
}

test('dashboard supports quick work logging into project ABC', async ({ page }) => {
  test.skip((page.viewportSize()?.width ?? 999) < 768, 'covered on desktop; mobile has dedicated layout coverage');
  const title = `E2E ABC work ${Date.now()}`;

  await login(page);
  await page.getByRole('button', { name: /quick add/i }).click();
  await expect(page.getByRole('heading', { name: 'Quick Add' })).toBeVisible();
  await page.locator('select').first().selectOption('work_log');
  await page.locator('input[placeholder="Title"]').fill(title);
  await page.locator('input[placeholder="Project"]').fill('ABC');
  await page.locator('input[placeholder="Minutes"]').fill('35');
  await page.locator('textarea[placeholder="Notes"]').fill('Completed today work for ABC.');
  await page.getByRole('button', { name: 'Capture' }).click();
  await expect(page.getByText('Captured.')).toBeVisible();

  await page.goto(`/work-logs?search=${encodeURIComponent(title)}&project=ABC`);
  await expect(page.getByRole('link', { name: new RegExp(title) }).first()).toBeVisible();
  await expect(page.locator('body')).toContainText('ABC');
});

test('creates a daily progress entry and renders smart links safely', async ({ page }) => {
  const title = `E2E progress ${Date.now()}`;

  await login(page);
  await page.goto('/daily-progress/create');
  const form = page.locator('form').filter({ has: page.getByRole('button', { name: 'Save entry' }) });
  await form.locator('input[type="date"]').fill(today);
  await form.locator('input').nth(1).fill(title);
  await form.locator('input').nth(2).fill('focused');
  await form.locator('input').nth(3).fill('e2e, abc');
  await form.locator('textarea').nth(0).fill('Implementing ProgressOS tests');
  await form.locator('textarea').nth(1).fill('Review Playwright traces');
  await form.locator('textarea').nth(3).fill('Created E2E coverage');
  await form.locator('textarea').nth(4).fill('[Spec](https://example.com/spec)');
  await form.getByRole('button', { name: 'Save entry' }).click();

  await expect(page.getByRole('heading', { name: title })).toBeVisible();
  const link = page.getByRole('link', { name: 'Spec' });
  await expect(link).toBeVisible();
  await expect(link).toHaveAttribute('href', 'https://example.com/spec');
  await expect(page.getByText(/00000z/i)).toHaveCount(0);
});

test('creates a task and updates its status from the operational list', async ({ page }) => {
  const title = `E2E task ${Date.now()}`;

  await login(page);
  await page.goto('/tasks/create');
  const form = page.locator('form').filter({ has: page.getByRole('button', { name: 'Save task' }) });
  await form.locator('input').first().fill(title);
  await form.locator('select').nth(0).selectOption({ label: 'ABC' });
  await form.locator('select').nth(2).selectOption('in_progress');
  await form.locator('select').nth(3).selectOption('high');
  await form.locator('input[type="date"]').fill(today);
  await form.locator('textarea').fill('[Ticket](https://example.com/ticket)');
  await form.getByRole('button', { name: 'Save task' }).click();
  await expect(page.getByRole('heading', { name: title })).toBeVisible();

  await page.goto(`/tasks?search=${encodeURIComponent(title)}`);
  const card = page.locator('article').filter({ hasText: title });
  await expect(card).toBeVisible();
  await card.getByRole('button', { name: 'done' }).click();
  await expect(card.getByRole('button', { name: 'done' })).toHaveClass(/bg-teal-700/);
});

test('mobile layout uses compact navigation and card lists', async ({ page }) => {
  test.skip((page.viewportSize()?.width ?? 999) >= 768, 'mobile-only responsive check');

  await login(page);
  await expect(page.getByRole('link', { name: /Home/ })).toBeVisible();
  await expect(page.getByRole('link', { name: /Logs/ })).toBeVisible();
  await page.locator('button[title="Quick add"]').first().click();
  await expect(page.getByRole('heading', { name: 'Quick Add' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Capture' })).toBeVisible();
  await page.goto('/work-logs');
  await expect(page.locator('table')).toBeHidden();
  await expect(page.locator('.mobile-record').first()).toBeVisible();
});
