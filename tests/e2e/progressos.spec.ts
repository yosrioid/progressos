import { expect, test, type Page } from '@playwright/test';

async function login(page: Page) {
  await page.goto('/login');
  await page.locator('input[type="email"]').fill('e2e@example.com');
  await page.locator('input[type="password"]').fill('password');
  await page.getByRole('button', { name: /log in/i }).click();
  await expect(page).toHaveURL(/\/dashboard/);
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
}

test('dashboard supports quick work logging into project ABC', async ({ page }) => {
  test.skip((page.viewportSize()?.width ?? 999) < 768, 'covered by the mobile quick-add check');
  const title = `E2E ABC work ${Date.now()}`;

  await login(page);
  await page.getByRole('button', { name: /quick add/i }).click();
  await expect(page.getByRole('heading', { name: 'Quick Add' })).toBeVisible();
  await page.locator('select').first().selectOption('work_log');
  await page.locator('input[placeholder="Title"]').fill(title);
  await page.locator('input[placeholder="Project"]').fill('ABC');
  await page.locator('input[type="number"]').fill('35');
  await page.locator('textarea[placeholder="Notes"]').fill('Completed today work for ABC.');
  await page.getByRole('button', { name: 'Capture' }).click();

  await expect(page).toHaveURL(/\/dashboard/);
  await page.goto('/work-logs');
  await expect(page.getByRole('heading', { name: 'Work Logs' })).toBeVisible();
  await expect(page.locator('article').filter({ hasText: title })).toBeVisible();
  await expect(page.locator('article').filter({ hasText: 'ABC' }).first()).toBeVisible();
});

test('records pages render API data without raw ISO date noise', async ({ page }) => {
  await login(page);
  await page.goto('/daily-progress');

  await expect(page.getByRole('heading', { name: 'Daily Progress' })).toBeVisible();
  await expect(page.locator('article').first()).toBeVisible();
  await expect(page.getByText(/00000z/i)).toHaveCount(0);

  await page.goto('/learning');
  await expect(page.getByRole('heading', { name: 'Learning', exact: true })).toBeVisible();
  await expect(page.locator('article').first()).toBeVisible();
});

test('global search and record filters are navigable', async ({ page }) => {
  await login(page);
  await page.locator('input[placeholder="Search everything"]').fill('baseline');
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/search\?q=baseline/);
  await expect(page.getByRole('heading', { name: 'Search ProgressOS' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Daily Progress' })).toBeVisible();

  await page.goto('/work-logs?search=baseline&category=feature');
  await expect(page.getByRole('heading', { name: 'Work Logs' })).toBeVisible();
  await expect(page.locator('article').first()).toBeVisible();
  await expect(page.getByText(/1 records/)).toBeVisible();
});

test('creates, edits, and opens a daily progress record through Vue forms', async ({ page }) => {
  test.skip((page.viewportSize()?.width ?? 999) < 768, 'desktop form flow');
  const title = `E2E progress form ${Date.now()}`;
  const updated = `${title} updated`;

  await login(page);
  await page.goto('/daily-progress/create');
  await expect(page.getByRole('heading', { name: 'New Daily Progress' })).toBeVisible();
  await page.locator('input[type="date"]').fill(new Date().toISOString().slice(0, 10));
  await page.getByRole('textbox', { name: 'Title' }).fill(title);
  await page.locator('textarea').nth(0).fill('Created from Vue form');
  await page.locator('textarea').last().fill('e2e, vue');
  await page.getByRole('button', { name: 'Save' }).click();

  await expect(page.getByRole('heading', { name: title })).toBeVisible();
  await page.getByRole('link', { name: 'Edit' }).click();
  await page.getByRole('textbox', { name: 'Title' }).fill(updated);
  await page.getByRole('button', { name: 'Save' }).click();

  await expect(page.getByRole('heading', { name: updated })).toBeVisible();
  await page.getByRole('link', { name: /Back to Daily Progress/ }).click();
  await expect(page.locator('article').filter({ hasText: updated })).toBeVisible();
});

test('mobile layout exposes compact navigation and usable quick capture', async ({ page }) => {
  test.skip((page.viewportSize()?.width ?? 999) >= 768, 'mobile-only responsive check');
  const title = `E2E mobile task ${Date.now()}`;

  await login(page);
  await expect(page.getByRole('link', { name: 'Dashboard' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Work', exact: true })).toBeVisible();

  await page.getByRole('button', { name: /quick add/i }).click();
  await expect(page.getByRole('heading', { name: 'Quick Add' })).toBeVisible();
  await page.locator('select').first().selectOption('task');
  await page.locator('input[placeholder="Title"]').fill(title);
  await page.getByRole('button', { name: 'Capture' }).click();

  await page.goto('/tasks');
  await expect(page.getByRole('heading', { name: 'Tasks' })).toBeVisible();
  await expect(page.locator('article').filter({ hasText: title })).toBeVisible();
});
