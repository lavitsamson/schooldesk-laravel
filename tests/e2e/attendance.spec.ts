import { expect, type Page, test } from '@playwright/test';

/*
 * Runs against the demo seed (php artisan migrate --seed), which records attendance up to
 * yesterday and leaves today open.
 */

const login = async (page: Page, email: string) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('DemoPass123!');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
};

test('a teacher takes attendance for their class', async ({ page }) => {
    await login(page, 'teacher@riverside.demo');

    await page.getByRole('link', { name: 'Attendance' }).first().click();
    const classes = page.getByRole('list', { name: 'Classes' });
    await expect(classes.getByText('Grade 7 A')).toBeVisible();

    await page
        .getByRole('link', { name: 'Take attendance for Grade 7 A' })
        .click();
    await expect(
        page.getByRole('heading', { name: /Grade 7 A ·/ }),
    ).toBeVisible();

    const pia = page.getByRole('row', { name: /Pia Parent/ });
    await pia.getByText('Absent', { exact: true }).click();
    await expect(pia.getByRole('radio', { name: 'Absent' })).toBeChecked();
    await pia.getByLabel('Note for Pia Parent').fill('Dentist');

    await page.getByRole('button', { name: 'Save attendance' }).click();
    await expect(
        page.getByText(/Attendance saved for Grade 7 A: \d+ absent\./),
    ).toBeVisible();

    await page.reload();
    await expect(
        page
            .getByRole('row', { name: /Pia Parent/ })
            .getByRole('radio', { name: 'Absent' }),
    ).toBeChecked();
});

test('a teacher reads the monthly report of their class', async ({ page }) => {
    await login(page, 'teacher@riverside.demo');

    await page.goto('/attendance');
    await page
        .getByRole('link', { name: 'Monthly report for Grade 7 A' })
        .click();
    await expect(page.getByText('Attendance rate')).toBeVisible();

    // The seed ends on the last weekday before today, which may be in the previous month.
    const lastSeeded = new Date();
    do {
        lastSeeded.setDate(lastSeeded.getDate() - 1);
    } while ([0, 6].includes(lastSeeded.getDay()));
    const month = lastSeeded.toLocaleString('en-GB', {
        month: 'long',
        year: 'numeric',
    });
    await page.getByLabel('Month').selectOption({ label: month });
    await expect(
        page.getByRole('heading', { name: `Grade 7 A · ${month}` }),
    ).toBeVisible();

    await expect(page.getByRole('link', { name: 'Export XLSX' })).toBeVisible();
    const students = page.getByRole('table', { name: /Students/ });
    await expect(
        students.getByRole('row', { name: /Pia Parent/ }),
    ).toBeVisible();
});

test('parents cannot take attendance', async ({ page }) => {
    await login(page, 'parent@riverside.demo');

    // The main navigation has no attendance taking; the family overview links to each
    // child's own attendance calendar instead.
    await expect(
        page
            .getByRole('navigation', { name: 'Main' })
            .getByRole('link', { name: 'Attendance' }),
    ).toHaveCount(0);
    const response = await page.goto('/attendance');
    expect(response?.status()).toBe(403);
});
