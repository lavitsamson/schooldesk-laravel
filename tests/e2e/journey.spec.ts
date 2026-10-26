import {
    type APIRequestContext,
    type Browser,
    expect,
    type Page,
    test,
} from '@playwright/test';
import { createHmac } from 'node:crypto';

/*
 * A new school's first weeks, end to end, with fresh data (it does not depend on the demo
 * seed): the admin registers and runs the setup wizard, invites a teacher, imports a student
 * with a guardian, invites the guardian to the portal and issues invoices; the teacher takes
 * attendance, enters marks and approves a report card; the parent reads the report card and
 * pays the invoice.
 *
 * Emails (verification, invitations) are read from Mailpit's API (E2E_MAILPIT_URL). The card
 * payment is reported the way Stripe reports it: a checkout.session.completed webhook signed
 * with STRIPE_WEBHOOK_SECRET (E2E_STRIPE_WEBHOOK_SECRET here), as in fees.spec.ts.
 * Locally, the queue must run (Horizon) and APP_URL must be the address Playwright uses,
 * because queued invitation mails build their signed links from it.
 */

const mailpit = process.env.E2E_MAILPIT_URL ?? 'http://127.0.0.1:8025';
const webhookSecret = process.env.E2E_STRIPE_WEBHOOK_SECRET;
const password = 'Journey-Pass-2026';

const run = `${Date.now()}${Math.random().toString(36).slice(2, 6)}`;
const admin = { name: 'Alma Admin', email: `admin-${run}@example.test` };
const teacher = { name: 'Theo Teacher', email: `teacher-${run}@example.test` };
const parent = { name: 'Petra Pupil', email: `parent-${run}@example.test` };

/** YYYY-MM-DD, `days` from today. */
const day = (days: number) => {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return date.toISOString().slice(0, 10);
};

/** The first link in the newest mail to `to` whose URL contains `path`. */
async function linkFromMail(
    request: APIRequestContext,
    to: string,
    path: string,
): Promise<string> {
    let link: string | undefined;
    await expect
        .poll(
            async () => {
                const search = await request.get(
                    `${mailpit}/api/v1/search?query=${encodeURIComponent(`to:"${to}"`)}`,
                );
                const { messages } = (await search.json()) as {
                    messages: { ID: string }[] | null;
                };
                if (!messages?.length) {
                    return null;
                }
                const message = await request.get(
                    `${mailpit}/api/v1/message/${messages[0]!.ID}`,
                );
                const { Text } = (await message.json()) as { Text: string };
                link = Text.match(/https?:\/\/[^\s)\]]+/g)?.find((url) =>
                    url.includes(path),
                );
                return link ?? null;
            },
            { timeout: 120_000, message: `a mail to ${to} with ${path}` },
        )
        .not.toBeNull();
    return link!;
}

/** Accepts an emailed invitation in a new browser, setting name and password. */
async function acceptInvitation(
    browser: Browser,
    request: APIRequestContext,
    person: { name: string; email: string },
): Promise<Page> {
    const link = await linkFromMail(request, person.email, '/invitations/');
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(link);
    await page.getByLabel('Your name').fill(person.name);
    await page.getByLabel('Choose a password').fill(password);
    await page.getByLabel('Confirm password').fill(password);
    await page.getByRole('button', { name: 'Accept invitation' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    return page;
}

test('a new school from registration to a paid invoice', async ({
    page,
    browser,
    request,
}) => {
    test.skip(!webhookSecret, 'E2E_STRIPE_WEBHOOK_SECRET is not set');
    test.setTimeout(20 * 60_000);

    await test.step('the admin registers and verifies the email address', async () => {
        await page.goto('/register');
        await page.getByLabel('School name').fill(`Journey School ${run}`);
        await page.getByLabel('Your name').fill(admin.name);
        await page.getByLabel('Email').fill(admin.email);
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByLabel('Confirm Password').fill(password);
        await page.getByRole('button', { name: /register/i }).click();
        await expect(page).toHaveURL(/\/verify-email$/);

        await page.goto(
            await linkFromMail(request, admin.email, '/verify-email/'),
        );
        await expect(page).toHaveURL(/\/setup\/school$/);
    });

    await test.step('the setup wizard', async () => {
        await page.getByRole('button', { name: 'Save and continue' }).click();

        await expect(page).toHaveURL(/\/setup\/academic-year$/);
        await page.getByLabel('Starts on').first().fill(day(-60));
        await page.getByLabel('Ends on').first().fill(day(300));
        const terms: [string, string][] = [
            [day(-60), day(60)],
            [day(61), day(180)],
            [day(181), day(300)],
        ];
        for (const [index, [startsOn, endsOn]] of terms.entries()) {
            await page.locator(`#term-${index}-starts`).fill(startsOn);
            await page.locator(`#term-${index}-ends`).fill(endsOn);
        }
        await page.getByRole('button', { name: 'Save and continue' }).click();

        await expect(page).toHaveURL(/\/setup\/classes$/);
        await page.getByLabel('Class 1', { exact: true }).fill('Grade 7');
        await page.getByLabel('Section', { exact: true }).fill('A');
        await page.getByRole('button', { name: 'Save and continue' }).click();

        await expect(page).toHaveURL(/\/setup\/subjects$/);
        await page.getByLabel('Subject 1', { exact: true }).fill('Mathematics');
        await page.getByLabel('Code', { exact: true }).fill('MATH');
        await page.getByRole('button', { name: 'Save and continue' }).click();

        await expect(page).toHaveURL(/\/setup\/grade-scale$/);
        await page.getByLabel('A–F (letter grades)').check();
        await page.getByRole('button', { name: 'Finish setup' }).click();
        await expect(page).toHaveURL(/\/dashboard$/);
    });

    let teacherPage: Page;
    await test.step('a teacher is invited, joins and is given the class', async () => {
        await page.goto('/members');
        await page.getByLabel('Name').fill(teacher.name);
        await page.getByLabel('Email').fill(teacher.email);
        await page.getByLabel('Role').selectOption({ label: 'Teacher' });
        await page.getByRole('button', { name: 'Send invitation' }).click();
        await expect(page.getByText(teacher.email)).toBeVisible();

        teacherPage = await acceptInvitation(browser, request, teacher);

        await page.goto('/classes');
        await page
            .getByRole('link', { name: /Grade 7 A/ })
            .first()
            .click();
        // The list page has a "Class teacher" field too (new class form).
        await expect(page).toHaveURL(/\/classes\/\d+$/);
        // Each save must finish before the next starts: Inertia cancels a running visit.
        const saved = (path: RegExp) =>
            page.waitForResponse(
                (response) =>
                    response.request().method() === 'PUT' &&
                    path.test(new URL(response.url()).pathname),
            );

        await page
            .getByLabel('Class teacher')
            .selectOption({ label: teacher.name });
        const details = saved(/^\/classes\/\d+$/);
        await page.getByRole('button', { name: 'Save', exact: true }).click();
        await details;

        await page
            .getByLabel('Mathematics (MATH)')
            .selectOption({ label: teacher.name });
        const assignments = saved(/^\/classes\/\d+\/assignments$/);
        await page
            .getByRole('button', { name: 'Save subject teachers' })
            .click();
        await assignments;

        await page.reload();
        await expect(page.getByLabel('Class teacher')).not.toHaveValue('');
        await expect(page.getByLabel('Mathematics (MATH)')).not.toHaveValue('');
    });

    await test.step('the admin imports a student with a guardian', async () => {
        const csv = [
            'admission_no,first_name,last_name,date_of_birth,class,guardian_name,guardian_email',
            `J-${run},Ida,Journey,17.05.2014,Grade 7 A,${parent.name},${parent.email}`,
        ].join('\n');

        await page.goto('/imports');
        await page.getByLabel('File', { exact: true }).setInputFiles({
            name: 'class.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from(csv),
        });
        await page.getByRole('button', { name: 'Import' }).click();
        await expect(
            page.getByText('1 of 1 rows processed (100%)'),
        ).toBeVisible({ timeout: 120_000 });
    });

    let parentPage: Page;
    await test.step('the guardian is invited to the portal and joins', async () => {
        await page.goto('/students');
        await page.getByRole('link', { name: 'Journey, Ida' }).click();
        await page.getByRole('button', { name: 'Invite to portal' }).click();

        parentPage = await acceptInvitation(browser, request, parent);
        await expect(
            parentPage.getByRole('heading', { name: 'My family' }),
        ).toBeVisible();
    });

    await test.step('the admin sets the tuition fee and issues invoices', async () => {
        await page.goto('/fees/structures');
        await page.getByLabel('Name').fill('Tuition');
        await page.getByLabel('Amount').fill('450');
        await page.getByRole('button', { name: 'Add', exact: true }).click();
        await expect(
            page.getByRole('cell', { name: 'Tuition' }).first(),
        ).toBeVisible();

        await page.goto('/fees');
        // The flash message can be gone by the time a slow machine looks; the parent's
        // invoice below shows the result.
        const generated = page.waitForResponse(
            (response) =>
                response.url().endsWith('/fees/generate') &&
                response.request().method() === 'POST',
        );
        await page
            .getByRole('button', { name: 'Generate invoices now' })
            .click();
        expect((await generated).status()).toBe(302);
    });

    await test.step('the teacher takes attendance and enters a mark', async () => {
        const t = teacherPage;
        await t.goto('/attendance');
        await t
            .getByRole('link', { name: 'Take attendance for Grade 7 A' })
            .click();
        await t.getByRole('button', { name: 'Save attendance' }).click();
        await expect(
            t.getByText('Attendance saved for Grade 7 A: everyone present.'),
        ).toBeVisible();

        await t.goto('/grades');
        await t.getByRole('link', { name: 'Mathematics in Grade 7 A' }).click();
        await t.getByRole('button', { name: 'Add assessment' }).click();
        const dialog = t.getByRole('dialog');
        await dialog.getByLabel('Name').fill('First test');
        const date = dialog.getByLabel('Date');
        // Today: Ida was enrolled today, so an earlier test would not list her.
        await date.fill(day(0));
        await dialog.getByLabel('Max marks').fill('20');
        await dialog
            .getByRole('button', { name: 'Add and enter marks' })
            .click();
        await t.getByRole('textbox', { name: /^Marks for / }).fill('18');
        await expect(t.getByTestId('save-status')).toHaveText(
            /All changes saved/,
        );
    });

    await test.step('the class teacher approves and publishes the report card', async () => {
        const t = teacherPage;
        await t.goto('/report-cards');
        await t.getByRole('link', { name: 'Grade 7 A' }).click();
        await t.getByRole('button', { name: 'Generate report cards' }).click();
        await expect(t.getByRole('progressbar')).toHaveCount(0, {
            timeout: 120_000,
        });
        await t
            .getByRole('row', { name: /Ida Journey/ })
            .getByRole('link')
            .click();

        await t
            .getByRole('textbox', { name: 'Comment' })
            .fill('Ida has made a confident start in Mathematics.');
        await t.getByRole('button', { name: 'Save comment' }).click();
        await expect(t.getByText('Comment saved.')).toBeVisible();
        await t.getByRole('button', { name: 'Approve' }).click();
        await expect(t.getByText('Report card approved.')).toBeVisible();
        t.once('dialog', (dialog) => dialog.accept());
        await t.getByRole('button', { name: 'Publish' }).click();
        await expect(t.getByText('Report card published.')).toBeVisible();
    });

    await test.step('the parent reads the report card and pays the invoice', async () => {
        const p = parentPage;
        await p.goto('/dashboard');
        await p.getByRole('link', { name: 'Report cards' }).first().click();
        const download = p
            .getByRole('list', { name: 'Report cards' })
            .getByRole('link', { name: /Download PDF/ })
            .first();
        const pdf = await p.request.get(
            (await download.getAttribute('href')) ?? '',
        );
        expect(pdf.headers()['content-type']).toBe('application/pdf');

        await p.goto('/family/invoices');
        await p
            .getByRole('list', { name: 'Invoices' })
            .getByRole('link', { name: /^Ida / })
            .click();
        await expect(p.getByText('Balance due')).toBeVisible();

        // Stripe Checkout reports the card payment.
        const invoiceId = /\/invoices\/(\d+)/.exec(p.url())?.[1];
        const schoolId = await p.evaluate(
            () =>
                (
                    window.history.state as {
                        page: { props: { school: { id: number } } };
                    }
                ).page.props.school.id,
        );
        const payload = JSON.stringify({
            id: `evt_journey_${run}`,
            type: 'checkout.session.completed',
            data: {
                object: {
                    id: `cs_test_journey_${run}`,
                    object: 'checkout.session',
                    payment_status: 'paid',
                    payment_intent: `pi_journey_${run}`,
                    amount_total: 45_000,
                    currency: 'eur',
                    metadata: {
                        school_id: String(schoolId),
                        invoice_id: String(invoiceId),
                    },
                },
            },
        });
        const timestamp = Math.floor(Date.now() / 1000);
        const signature = createHmac('sha256', webhookSecret ?? '')
            .update(`${timestamp}.${payload}`)
            .digest('hex');
        const webhook = await p.request.post('/webhooks/stripe', {
            data: payload,
            headers: {
                'Content-Type': 'application/json',
                'Stripe-Signature': `t=${timestamp},v1=${signature}`,
            },
        });
        expect(webhook.status()).toBe(200);

        await p.reload();
        await expect(
            p.getByRole('heading', { name: /^Invoice SD-.*Paid/ }),
        ).toBeVisible();
        const receipt = await p.request.get(
            (await p
                .getByRole('link', { name: 'Receipt' })
                .last()
                .getAttribute('href')) ?? '',
        );
        expect(receipt.headers()['content-type']).toBe('application/pdf');
    });
});
