import { expect, test, type Page } from '@playwright/test';

async function login(page: Page) {
  await page.goto('/login');
  await page.locator('input[type="email"]').fill('e2e@example.com');
  await page.locator('input[type="password"]').fill('password');
  await page.getByRole('button', { name: /log in/i }).click();
  await expect(page).toHaveURL(/\/dashboard/);
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
}

async function pastePlainText(page: Page, selector: string, text: string) {
  await page.locator(selector).evaluate((node, value) => {
    const event = new Event('paste', { bubbles: true, cancelable: true });
    Object.defineProperty(event, 'clipboardData', {
      value: { getData: (type: string) => type === 'text/plain' ? value : '' },
    });
    node.dispatchEvent(event);
  }, text);
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

test('command palette supports keyboard navigation shortcuts', async ({ page }) => {
  test.skip((page.viewportSize()?.width ?? 999) < 768, 'desktop command palette flow');

  await login(page);
  await page.keyboard.press(process.platform === 'darwin' ? 'Meta+K' : 'Control+K');
  await expect(page.getByPlaceholder('Jump to a page or create something')).toBeVisible();
  await page.getByPlaceholder('Jump to a page or create something').fill('New Work Log');
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/work-logs\/create/);
  await expect(page.getByRole('heading', { name: 'New Work Log' })).toBeVisible();
});

test('profile avatar crop upload updates the header avatar', async ({ page }) => {
  test.skip((page.viewportSize()?.width ?? 999) < 768, 'desktop avatar crop flow');
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAAFElEQVR4nGP8z8AARLJgwiM3gqUBAJ4CBQGd+0w1AAAAAElFTkSuQmCC',
    'base64',
  );

  await login(page);
  await page.goto('/profile');
  await expect(page.getByRole('heading', { name: 'Profile & Settings' })).toBeVisible();
  await page.locator('input[type="file"]').setInputFiles({ name: 'avatar.png', mimeType: 'image/png', buffer: png });
  await expect(page.getByRole('heading', { name: 'Crop photo' })).toBeVisible();
  await expect(page.getByAltText('Avatar crop preview')).toBeVisible();
  const cropBox = page.getByTestId('avatar-crop-box');
  const bounds = await cropBox.boundingBox();
  if (bounds) {
    await page.mouse.move(bounds.x + bounds.width / 2, bounds.y + bounds.height / 2);
    await page.mouse.down();
    await page.mouse.move(bounds.x + bounds.width / 2 + 12, bounds.y + bounds.height / 2 + 8);
    await page.mouse.up();
  }
  await page.getByRole('button', { name: 'Use crop' }).click();
  await page.getByRole('button', { name: 'Save avatar' }).click();
  await expect(page.getByText('Avatar updated', { exact: true })).toBeVisible();
  await expect(page.locator('header img').first()).toBeVisible();
});

test('pasting a URL over selected textarea text preserves label as markdown link', async ({ page }) => {
  test.skip((page.viewportSize()?.width ?? 999) < 768, 'desktop paste flow');
  const title = `E2E link paste ${Date.now()}`;
  const textarea = page.locator('textarea').nth(4);

  await login(page);
  await page.goto('/daily-progress/create');
  await page.locator('input[type="date"]').fill(new Date().toISOString().slice(0, 10));
  await page.getByRole('textbox', { name: 'Title' }).fill(title);
  await textarea.fill('Spec');
  await textarea.focus();
  await textarea.evaluate((node: HTMLTextAreaElement) => node.select());
  await pastePlainText(page, 'textarea >> nth=4', 'https://example.com/spec');
  await expect(textarea).toHaveValue('[Spec](https://example.com/spec)');
  await page.getByRole('button', { name: 'Save' }).click();

  await expect(page.getByRole('heading', { name: title })).toBeVisible();
  await expect(page.getByText('Daily Progress created')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Spec' })).toHaveAttribute('href', 'https://example.com/spec');
});

test('quick add notes paste URL over selected text preserves label', async ({ page }) => {
  test.skip((page.viewportSize()?.width ?? 999) < 768, 'desktop paste flow');

  await login(page);
  await page.getByRole('button', { name: /quick add/i }).click();
  const notes = page.locator('textarea[placeholder="Notes"]');
  await notes.fill('Ticket');
  await notes.focus();
  await notes.evaluate((node: HTMLTextAreaElement) => node.select());
  await pastePlainText(page, 'textarea[placeholder="Notes"]', 'https://example.com/ticket');
  await expect(notes).toHaveValue('[Ticket](https://example.com/ticket)');
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
  await expect(page.getByText('Daily Progress updated')).toBeVisible();
  await page.getByRole('link', { name: /Back to Daily Progress/ }).click();
  await expect(page.locator('article').filter({ hasText: updated })).toBeVisible();
});

test('delete uses themed confirmation and success notification', async ({ page }) => {
  test.skip((page.viewportSize()?.width ?? 999) < 768, 'desktop confirmation flow');
  const title = `E2E delete confirm ${Date.now()}`;

  await login(page);
  await page.goto('/daily-progress/create');
  await page.locator('input[type="date"]').fill(new Date().toISOString().slice(0, 10));
  await page.getByRole('textbox', { name: 'Title' }).fill(title);
  await page.getByRole('button', { name: 'Save' }).click();
  await expect(page.getByRole('heading', { name: title })).toBeVisible();

  await page.getByRole('button', { name: 'Delete' }).click();
  await expect(page.getByRole('heading', { name: /Delete daily progress/i })).toBeVisible();
  await page.getByRole('button', { name: 'Cancel' }).click();
  await expect(page.getByRole('heading', { name: title })).toBeVisible();

  await page.getByRole('button', { name: 'Delete' }).click();
  await page.getByRole('button', { name: 'Delete', exact: true }).last().click();
  await expect(page).toHaveURL(/\/daily-progress$/);
  await expect(page.getByText('Daily Progress deleted')).toBeVisible();
});

test('mobile layout exposes compact navigation and usable quick capture', async ({ page }) => {
  test.skip((page.viewportSize()?.width ?? 999) >= 768, 'mobile-only responsive check');
  const title = `E2E mobile task ${Date.now()}`;

  await login(page);
  await page.getByRole('button', { name: 'Open menu' }).click();
  await expect(page.getByRole('dialog', { name: 'Navigation menu' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Dashboard' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Work Logs' })).toBeVisible();
  await page.getByRole('button', { name: 'Close menu' }).last().click();

  await page.getByRole('button', { name: /quick add/i }).click();
  await expect(page.getByRole('heading', { name: 'Quick Add' })).toBeVisible();
  await page.locator('select').first().selectOption('task');
  await page.locator('input[placeholder="Title"]').fill(title);
  await page.getByRole('button', { name: 'Capture' }).click();

  await page.goto('/tasks');
  await expect(page.getByRole('heading', { name: 'Tasks' })).toBeVisible();
  await expect(page.locator('article').filter({ hasText: title })).toBeVisible();
});
