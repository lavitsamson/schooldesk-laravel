import { expect, type Page, test } from '@playwright/test';

/*
 * Runs against the demo seed (php artisan migrate --seed).
 */

const login = async (page: Page, email: string) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('DemoPass123!');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
};

test('an admin finds a student and sees their guardians', async ({ page }) => {
    await login(page, 'admin@riverside.demo');

    await page.getByRole('link', { name: 'Students' }).first().click();
    const table = page.getByRole('table', { name: 'Students' });
    await expect(table.getByRole('row')).toHaveCount(26); // header + 25 per page

    await page.getByLabel('Search').fill('pia parent');
    await expect(table.getByRole('row')).toHaveCount(2);
    await table.getByRole('link', { name: 'Parent, Pia' }).click();

    await expect(
        page.getByRole('heading', { name: 'Pia Parent' }),
    ).toBeVisible();
    await expect(page.getByText('Grade 7 A').first()).toBeVisible();
    const guardians = page.getByRole('region', { name: 'Guardians' });
    await expect(guardians.getByText('Paula Parent')).toBeVisible();
    await expect(guardians.getByText('Primary contact')).toBeVisible();
});

test('an admin adds a student and enrols them in a class', async ({ page }) => {
    // The local database is not reset between runs, so the admission number must be new.
    const admissionNo = `E2E-${Date.now()}`;
    await login(page, 'admin@riverside.demo');

    await page.goto('/students/create');
    await page.getByLabel('First name').fill('Ezra');
    await page.getByLabel('Last name').fill('Playwright');
    await page.getByLabel('Admission number').fill(admissionNo);
    await page.getByLabel('Date of birth').fill('2014-05-17');
    await page
        .getByLabel('Class (current year)')
        .selectOption({ label: 'Grade 6 B' });
    await page.getByRole('button', { name: 'Add student' }).click();

    await expect(
        page.getByRole('heading', { name: 'Ezra Playwright' }),
    ).toBeVisible();
    await expect(page.getByText('Ezra Playwright added.')).toBeVisible();
    await expect(page.getByText(admissionNo).first()).toBeVisible();
});

test('a teacher cannot manage students', async ({ page }) => {
    await login(page, 'teacher@riverside.demo');

    await expect(page.getByRole('link', { name: 'Students' })).toHaveCount(0);
    const response = await page.goto('/students');
    expect(response?.status()).toBe(403);
});
