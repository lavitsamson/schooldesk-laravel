import { expect, test } from '@playwright/test';

test('login page renders', async ({ page }) => {
    await page.goto('/login');

    await expect(page.getByLabel('Email')).toBeVisible();
    await expect(page.getByLabel('Password')).toBeVisible();
    await expect(page.getByRole('button', { name: /log in/i })).toBeVisible();
});

test('a new user can register and is asked to verify their email', async ({
    page,
}) => {
    const email = `e2e-${Date.now()}-${Math.random().toString(36).slice(2, 8)}@example.test`;

    await page.goto('/register');
    await page.getByLabel('Name').fill('E2E User');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('DemoPass123!');
    await page.getByLabel('Confirm Password').fill('DemoPass123!');
    await page.getByRole('button', { name: /register/i }).click();

    await expect(page).toHaveURL(/\/verify-email$/);
    await expect(page.getByText(/thanks for signing up/i)).toBeVisible();
});
