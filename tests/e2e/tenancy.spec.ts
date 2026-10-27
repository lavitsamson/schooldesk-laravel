import { expect, type Page, test } from '@playwright/test';

/*
 * Runs against the demo seed (php artisan migrate --seed).
 */

const login = async (page: Page, email: string) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('DemoPass123!');
    await page.getByRole('button', { name: /log in/i }).click();
};

test('a school admin sees their school and its members', async ({ page }) => {
    await login(page, 'admin@riverside.demo');

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(
        page.getByRole('heading', { name: 'Riverside International School' }),
    ).toBeVisible();

    const nav = page.getByRole('navigation', { name: 'Main' });
    await nav.getByRole('button', { name: 'School' }).click();
    await nav.getByRole('link', { name: 'Members' }).first().click();

    const members = page.getByRole('table', { name: 'School members' });
    await expect(members.getByText('teacher@riverside.demo')).toBeVisible();
    await expect(members.getByText('parent@riverside.demo')).toBeVisible();
    // The other demo school's admin must never show up here.
    await expect(members.getByText('admin@gymnasium.demo')).toHaveCount(0);
});

test('a teacher has no access to member management', async ({ page }) => {
    await login(page, 'teacher@riverside.demo');

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByRole('link', { name: 'Members' })).toHaveCount(0);

    const response = await page.goto('/members');
    expect(response?.status()).toBe(403);
});
