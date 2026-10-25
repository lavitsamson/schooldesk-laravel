import { expect, type Page, test } from '@playwright/test';

/*
 * Runs against the demo seed (php artisan migrate --seed). Tom Teacher is class teacher of
 * Grade 7 A, whose report cards are seeded at every stage; AI comment drafts are on for
 * Riverside and written by the offline fake (no ANTHROPIC_API_KEY in CI).
 */

const login = async (page: Page, email: string) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('DemoPass123!');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
};

test('the class teacher drafts a comment with AI, approves and publishes a report card', async ({
    page,
}) => {
    await login(page, 'teacher@riverside.demo');

    await page.getByRole('link', { name: 'Report cards' }).first().click();
    await page.getByRole('link', { name: 'Grade 7 A' }).click();
    await expect(
        page.getByRole('heading', { name: /^Grade 7 A ·/ }),
    ).toBeVisible();

    // Regenerating updates the drafts and finishes (sync queue in CI, Horizon locally).
    await page
        .getByRole('button', { name: /regenerate report cards/i })
        .click();
    await expect(
        page.getByText(/^Generating \d+ report cards\.$/),
    ).toBeVisible();
    await expect(page.getByRole('progressbar')).toHaveCount(0);

    // The first student whose comment is still missing.
    const row = page
        .getByRole('row')
        .filter({ has: page.getByRole('cell', { name: 'Missing' }) })
        .first();
    const student = (await row.getByRole('rowheader').innerText()).trim();
    const firstName = student.split(' ')[0] ?? student;
    await row.getByRole('link').click();
    await expect(
        page.getByRole('heading', { name: new RegExp(`^${student} ·`) }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Draft comment' }).click();
    await expect(page.getByTestId('ai-draft')).toContainText(firstName);
    await page.getByRole('button', { name: 'Use this draft' }).click();

    const comment = page.getByRole('textbox', { name: 'Comment' });
    await expect(comment).toBeFocused();
    await expect(
        page.getByText('From the AI draft: check and edit it before saving.'),
    ).toBeVisible();
    await comment.press('End');
    await comment.pressSequentially(' A pleasure to teach.');
    await page.getByRole('button', { name: 'Save comment' }).click();
    await expect(page.getByText('Comment saved.')).toBeVisible();
    await expect(page.getByText('Started from an AI draft.')).toBeVisible();

    await page.getByRole('button', { name: 'Approve' }).click();
    await expect(page.getByText('Report card approved.')).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Download PDF' }),
    ).toBeVisible();
    await expect(comment).not.toBeEditable();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Publish' }).click();
    await expect(page.getByText('Report card published.')).toBeVisible();
    await expect(page.getByText(/Published to parents/)).toBeVisible();

    const pdf = await page.request.get(
        (await page
            .getByRole('link', { name: 'Download PDF' })
            .getAttribute('href')) ?? '',
    );
    expect(pdf.status()).toBe(200);
    expect(pdf.headers()['content-type']).toBe('application/pdf');
});

test('parents cannot open the staff report card pages', async ({ page }) => {
    await login(page, 'parent@riverside.demo');

    // Parents read published report cards in the portal (parent-portal.spec.ts).
    await expect(
        page
            .getByRole('navigation', { name: 'Main' })
            .getByRole('link', { name: 'Report cards' }),
    ).toHaveCount(0);
    const response = await page.goto('/report-cards');
    expect(response?.status()).toBe(403);
});
