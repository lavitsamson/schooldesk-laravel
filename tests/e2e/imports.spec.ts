import { expect, test } from '@playwright/test';

/*
 * Runs against the demo seed. In CI the queue is synchronous, so the import finishes inside
 * the upload request; locally, Horizon (or queue:work) must be running.
 */

test('an admin imports students and downloads the error report', async ({
    page,
}) => {
    // The local database is not reset between runs, so the admission number must be new.
    const admissionNo = `IMP-${Date.now()}`;
    const csv = [
        'admission_no,first_name,last_name,date_of_birth,class,guardian_name,guardian_email',
        `${admissionNo},Ida,Import,17.05.2014,grade 6 b,Ines Import,ines.${admissionNo.toLowerCase()}@example.com`,
        `${admissionNo}-X,Otto,Oops,31.02.2014,,,`,
    ].join('\n');

    await page.goto('/login');
    await page.getByLabel('Email').fill('admin@riverside.demo');
    await page.getByLabel('Password').fill('DemoPass123!');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto('/students');
    await page.getByRole('link', { name: 'Import students' }).click();
    await expect(
        page.getByRole('heading', { name: 'Import students' }),
    ).toBeVisible();

    await page.getByLabel('File', { exact: true }).setInputFiles({
        name: 'roster.csv',
        mimeType: 'text/csv',
        buffer: Buffer.from(csv),
    });
    await page.getByRole('button', { name: 'Import' }).click();

    await expect(
        page.getByRole('heading', { name: 'Import: roster.csv' }),
    ).toBeVisible();
    await expect(page.getByText('Completed', { exact: true })).toBeVisible();
    await expect(page.getByText('2 of 2 rows processed (100%)')).toBeVisible();

    const failures = page.getByRole('table');
    await expect(failures.getByText(`${admissionNo}-X`)).toBeVisible();
    await expect(
        failures.getByText(
            'The date of birth must be a date like 2014-05-17 or 17.05.2014.',
        ),
    ).toBeVisible();

    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Download error report' }).click();
    expect((await download).suggestedFilename()).toBe('roster-errors.csv');

    await page.getByRole('link', { name: 'View students' }).click();
    await page.getByLabel('Search').fill(admissionNo);
    const students = page.getByRole('table', { name: 'Students' });
    await expect(students.getByRole('row')).toHaveCount(2);
    await expect(students.getByText('Grade 6 B')).toBeVisible();
});

test('an admin exports selected students to Excel', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('admin@riverside.demo');
    await page.getByLabel('Password').fill('DemoPass123!');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto('/students');
    await page.getByLabel('Select all students on this page').check();
    await page
        .getByLabel('Action', { exact: true })
        .selectOption({ label: 'Export selected (Excel)' });

    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Apply' }).click();
    expect((await download).suggestedFilename()).toMatch(
        /^students-\d{4}-\d{2}-\d{2}\.xlsx$/,
    );
});
