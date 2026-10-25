import { expect, type Page, test } from '@playwright/test';
import { createHmac } from 'node:crypto';

/*
 * Runs against the demo seed (php artisan migrate --seed): Riverside's autumn invoices in
 * mixed states. Stripe is not called: the card payment is simulated by posting a
 * checkout.session.completed event signed with the app's STRIPE_WEBHOOK_SECRET, exactly as
 * Stripe would send it. Set the same secret in E2E_STRIPE_WEBHOOK_SECRET (CI does both).
 */

const webhookSecret = process.env.E2E_STRIPE_WEBHOOK_SECRET;

const login = async (page: Page, email: string) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('DemoPass123!');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
};

/** The amount prefilled in "Record a payment", in minor units ("1630.00" → 163000). */
const prefilledBalance = async (page: Page) =>
    Math.round(Number(await page.getByLabel('Amount').inputValue()) * 100);

// Inertia keeps the current page (with the shared props) in the history state; the
// data-page attribute only has the first page loaded, here the login page.
const currentSchoolId = (page: Page) =>
    page.evaluate(
        () =>
            (
                window.history.state as {
                    page: { props: { school: { id: number } } };
                }
            ).page.props.school.id,
    );

test('an admin records a cash payment and Stripe confirms the rest', async ({
    page,
}) => {
    test.skip(!webhookSecret, 'E2E_STRIPE_WEBHOOK_SECRET is not set');

    await login(page, 'admin@riverside.demo');
    await page.getByRole('link', { name: 'Fees' }).first().click();
    await expect(page.getByRole('heading', { name: 'Fees' })).toBeVisible();
    await expect(
        page.getByRole('columnheader', { name: 'Collected' }),
    ).toBeVisible();

    // An invoice nobody has paid anything on yet.
    await page.getByRole('link', { name: 'All invoices' }).click();
    await page.getByLabel('Status').selectOption('issued');
    await expect(page).toHaveURL(/status=issued/);
    await page
        .getByRole('row')
        .nth(1)
        .getByRole('rowheader')
        .getByRole('link')
        .click();
    await expect(
        page.getByRole('heading', { name: /^Invoice SD-\d{4}-\d{6}/ }),
    ).toBeVisible();

    const invoiceId = Number(/\/invoices\/(\d+)/.exec(page.url())?.[1]);
    const total = await prefilledBalance(page);

    await page.getByLabel('Amount').fill('100');
    await page.getByLabel('Method').selectOption('cash');
    await page.getByRole('button', { name: 'Record payment' }).click();
    await expect(page.getByText(/^Payment recorded\./)).toBeVisible();
    await expect(page.getByText('Partially paid').first()).toBeVisible();
    await expect.poll(() => prefilledBalance(page)).toBe(total - 10_000);

    // Stripe reports the card payment of the rest.
    const event = {
        id: `evt_e2e_${Date.now()}`,
        type: 'checkout.session.completed',
        data: {
            object: {
                id: `cs_test_e2e_${Date.now()}`,
                object: 'checkout.session',
                payment_status: 'paid',
                payment_intent: `pi_e2e_${Date.now()}`,
                amount_total: total - 10_000,
                currency: 'eur',
                metadata: {
                    school_id: String(await currentSchoolId(page)),
                    invoice_id: String(invoiceId),
                },
            },
        },
    };
    const payload = JSON.stringify(event);
    const timestamp = Math.floor(Date.now() / 1000);
    const signature = createHmac('sha256', webhookSecret ?? '')
        .update(`${timestamp}.${payload}`)
        .digest('hex');

    const forged = await page.request.post('/webhooks/stripe', {
        data: payload,
        headers: {
            'Content-Type': 'application/json',
            'Stripe-Signature': `t=${timestamp},v1=${'0'.repeat(64)}`,
        },
    });
    expect(forged.status()).toBe(400);

    const webhook = await page.request.post('/webhooks/stripe', {
        data: payload,
        headers: {
            'Content-Type': 'application/json',
            'Stripe-Signature': `t=${timestamp},v1=${signature}`,
        },
    });
    expect(webhook.status()).toBe(200);

    await page.reload();
    await expect(
        page.getByRole('heading', { name: /^Invoice SD-.*Paid/ }),
    ).toBeVisible();
    await expect(page.getByTestId('balance')).toHaveText(/0[.,]00/);
    await expect(
        page.getByRole('cell', { name: 'Card (Stripe)' }),
    ).toBeVisible();

    const receipt = await page.request.get(
        (await page
            .getByRole('link', { name: 'Receipt' })
            .last()
            .getAttribute('href')) ?? '',
    );
    expect(receipt.status()).toBe(200);
    expect(receipt.headers()['content-type']).toBe('application/pdf');
});

test("a parent sees their children's invoices only", async ({ page }) => {
    await login(page, 'parent@riverside.demo');

    await page.getByRole('link', { name: 'Invoices' }).first().click();
    const invoices = page.getByRole('list', { name: 'Invoices' });
    await expect(invoices.getByRole('listitem')).toHaveCount(2);
    await expect(invoices.getByText('Partially paid')).toBeVisible();

    await invoices.getByRole('link', { name: /^Lotta / }).click();
    await expect(page.getByText('Balance due')).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Record payment' }),
    ).toHaveCount(0);

    const pdf = await page.request.get(
        (await page
            .getByRole('link', { name: 'Download PDF' })
            .getAttribute('href')) ?? '',
    );
    expect(pdf.status()).toBe(200);
    expect(pdf.headers()['content-type']).toBe('application/pdf');

    const response = await page.goto('/fees');
    expect(response?.status()).toBe(403);
});
