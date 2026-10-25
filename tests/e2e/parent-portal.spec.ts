import { expect, type Page, test } from '@playwright/test';

/*
 * Runs against the demo seed (php artisan migrate --seed): Paula Parent has two children at
 * Riverside, Pia (Grade 7 A, with a published report card) and Lotta (Grade 5 B), and a few
 * notifications, three of them unread.
 */

const login = async (page: Page, email: string) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('DemoPass123!');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
};

test('a parent follows their children in the portal', async ({ page }) => {
    await login(page, 'parent@riverside.demo');

    // The family overview is the parent's dashboard.
    await expect(
        page.getByRole('heading', { name: 'My family' }),
    ).toBeVisible();
    const children = page.getByRole('list', { name: 'Children' });
    await expect(
        children.getByRole('heading', { name: 'Pia Parent' }),
    ).toBeVisible();
    await expect(
        children.getByRole('heading', { name: 'Lotta Parent' }),
    ).toBeVisible();

    // Attendance: a month calendar, with the previous month one click away.
    await page
        .getByRole('navigation', { name: "Pia's pages" })
        .getByRole('link', { name: 'Attendance' })
        .click();
    await expect(
        page.getByRole('heading', { name: /Pia Parent/ }),
    ).toBeVisible();
    await expect(
        page.getByRole('list', { name: /^Attendance in / }),
    ).toBeVisible();
    await expect(page.getByText('Attendance rate')).toBeVisible();
    await page.getByRole('link', { name: '‹ Previous' }).click();
    await expect(page).toHaveURL(/month=\d{4}-\d{2}/);

    // Results so far in the current term.
    await page
        .getByRole('navigation', { name: 'Child pages' })
        .getByRole('link', { name: 'Results' })
        .click();
    await expect(page.getByText('Overall result')).toBeVisible();
    await expect(page.getByText(/Results so far in the/)).toBeVisible();

    // Pia's published report card, as a PDF.
    await page
        .getByRole('navigation', { name: 'Child pages' })
        .getByRole('link', { name: 'Report cards' })
        .click();
    const download = page
        .getByRole('list', { name: 'Report cards' })
        .getByRole('link', { name: /Download PDF/ })
        .first();
    await expect(download).toBeVisible();
    const pdf = await page.request.get(
        (await download.getAttribute('href')) ?? '',
    );
    expect(pdf.status()).toBe(200);
    expect(pdf.headers()['content-type']).toBe('application/pdf');

    // The sibling switcher keeps the page: Lotta has no report card yet.
    await page
        .getByRole('navigation', { name: 'Children' })
        .getByRole('link', { name: 'Lotta' })
        .click();
    await expect(
        page.getByRole('heading', { name: /Lotta Parent/ }),
    ).toBeVisible();
    await expect(
        page.getByText('No report cards have been published for Lotta yet.'),
    ).toBeVisible();
});

test('a parent reads their notifications', async ({ page }) => {
    await login(page, 'parent@riverside.demo');

    await page
        .getByRole('link', { name: /^Notifications/ })
        .first()
        .click();
    await expect(
        page.getByRole('heading', { name: 'Notifications' }),
    ).toBeVisible();
    const list = page.getByRole('list', { name: 'Notifications' });
    await expect(list.getByRole('button').first()).toBeVisible();

    // Opening a notification marks it read and leads to what it is about.
    await list
        .getByRole('button', { name: /report card/ })
        .first()
        .click();
    await expect(page).toHaveURL(/\/family\/children\/\d+\/report-cards$/);

    await page
        .getByRole('link', { name: /^Notifications/ })
        .first()
        .click();
    const markAll = page.getByRole('button', { name: 'Mark all as read' });
    if (await markAll.isVisible()) {
        await markAll.click();
        await expect(
            page.getByText('All notifications marked as read.'),
        ).toBeVisible();
    }
    await expect(markAll).toBeHidden();
    await expect(list.getByText('(unread)')).toHaveCount(0);
});
