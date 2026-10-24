import { expect, type Page, test } from '@playwright/test';

/*
 * Runs against the demo seed (php artisan migrate --seed). Tom Teacher teaches Mathematics
 * in Grade 7 A and is its class teacher; Pia Parent is one of its students.
 */

const login = async (page: Page, email: string) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('DemoPass123!');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
};

test('a teacher adds an assessment and enters marks in the grid', async ({
    page,
}) => {
    await login(page, 'teacher@riverside.demo');

    await page.getByRole('link', { name: 'Grades' }).first().click();
    await page
        .getByRole('link', { name: 'Mathematics in Grade 7 A' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Mathematics · Grade 7 A' }),
    ).toBeVisible();

    const name = `Quick quiz ${Date.now()}`;
    await page.getByRole('button', { name: 'Add assessment' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Name').fill(name);
    // The first day of the term is always a valid date, whenever the test runs.
    const date = dialog.getByLabel('Date');
    await date.fill((await date.getAttribute('min')) ?? '');
    await dialog.getByLabel('Max marks').fill('20');
    await dialog.getByRole('button', { name: 'Add and enter marks' }).click();

    await expect(page.getByRole('heading', { name })).toBeVisible();
    await expect(
        page.getByText(`${name} added. Enter the marks below.`),
    ).toBeVisible();

    const marks = page.getByRole('textbox', { name: /^Marks for / });
    // The grid starts in the first empty cell; Enter moves down like a spreadsheet.
    await expect(marks.first()).toBeFocused();
    await page.keyboard.type('17,5');
    await page.keyboard.press('Enter');
    await expect(marks.nth(1)).toBeFocused();
    await page.keyboard.type('25');
    await expect(page.getByText('At most 20 marks.')).toBeVisible();
    await expect(page.getByTestId('save-status')).toHaveText(
        /not valid and were not saved/,
    );

    await marks.nth(1).fill('12');
    await expect(page.getByTestId('save-status')).toHaveText(
        /All changes saved/,
    );

    await page.reload();
    await expect(marks.first()).toHaveValue('17.5');
    await expect(marks.nth(1)).toHaveValue('12');

    await page
        .getByRole('navigation', { name: 'Breadcrumb' })
        .getByRole('link', { name: 'Mathematics · Grade 7 A' })
        .click();
    const row = page
        .getByRole('table', { name: 'Assessments' })
        .getByRole('row', { name: new RegExp(name) });
    await expect(row.getByRole('cell', { name: /^2 \/ \d+$/ })).toBeVisible();
});

test('the class teacher reads the class results and a student\'s result', async ({
    page,
}) => {
    await login(page, 'teacher@riverside.demo');

    await page.goto('/grades');
    await page.getByRole('link', { name: 'Results of Grade 7 A' }).click();
    await expect(
        page.getByRole('heading', { name: /^Results · Grade 7 A ·/ }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Pia Parent' }).click();
    await expect(
        page.getByRole('heading', { name: /^Pia Parent ·/ }),
    ).toBeVisible();
    await expect(page.getByText('Overall result')).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Grade scale' }),
    ).toBeVisible();
});

test('parents cannot see grades', async ({ page }) => {
    await login(page, 'parent@riverside.demo');

    await expect(page.getByRole('link', { name: 'Grades' })).toHaveCount(0);
    const response = await page.goto('/grades');
    expect(response?.status()).toBe(403);
});
