import { expect, type Page, test } from '@playwright/test';

/*
 * The README's screenshots (docs/screenshots), taken from the demo seed. Not a test: skipped
 * unless SCREENSHOTS=1, e.g. against the production stack (docs/DEPLOYMENT.md):
 *
 *   SCREENSHOTS=1 E2E_BASE_URL=http://localhost:8088 npx playwright test screenshots
 */
test.skip(
    !process.env.SCREENSHOTS,
    'Set SCREENSHOTS=1 to update docs/screenshots.',
);

type Path = string | ((page: Page) => Promise<string>);

interface Shot {
    name: string;
    email: string | null;
    path: Path;
    colorScheme?: 'light' | 'dark';
    mobile?: boolean;
    fullPage?: boolean;
    /** Runs after the page has loaded, before the screenshot. */
    then?: (page: Page) => Promise<void>;
}

const login = async (page: Page, email: string) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('DemoPass123!');
    await page.getByRole('button', { name: /log in|anmelden/i }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
};

/** The first link in the page's main content whose path matches. */
const linkFrom =
    (from: Path, pattern: RegExp): Path =>
    async (page) => {
        await page.goto(typeof from === 'string' ? from : await from(page));
        const urls = await page
            .locator('main a[href]')
            .evaluateAll((links) =>
                links.map((a) => (a as HTMLAnchorElement).href),
            );
        const url = urls
            .map((href) => new URL(href))
            .find((u) => pattern.test(u.pathname));
        if (url === undefined) {
            throw new Error(`No link matching ${pattern}`);
        }
        return url.pathname + url.search;
    };

// Grade 7 A has report cards at every stage in the demo seed.
const reportCardClass: Path = async (page) => {
    await page.goto('/report-cards');
    const href = await page
        .locator('main a', { hasText: 'Grade 7 A' })
        .first()
        .getAttribute('href');
    if (href === null) {
        throw new Error('No report cards for Grade 7 A');
    }
    return (
        new URL(href, page.url()).pathname + new URL(href, page.url()).search
    );
};

const shots: Shot[] = [
    { name: 'landing', email: null, path: '/' },
    { name: 'students', email: 'admin@riverside.demo', path: '/students' },
    {
        name: 'attendance-grid',
        email: 'teacher@riverside.demo',
        path: linkFrom('/attendance', /^\/attendance\/\d+\/\d{4}-\d{2}-\d{2}$/),
    },
    {
        name: 'attendance-report',
        email: 'admin@riverside.demo',
        path: linkFrom('/attendance', /^\/attendance\/\d+\/report$/),
    },
    {
        name: 'mark-entry',
        email: 'teacher@riverside.demo',
        path: linkFrom(
            linkFrom('/grades', /^\/grades\/\d+\/\d+$/),
            /^\/assessments\/\d+\/grades$/,
        ),
        // The grid focuses the first empty cell, which scrolls the page.
        then: async (page) => {
            await page.evaluate(() => window.scrollTo(0, 0));
        },
    },
    {
        name: 'class-results',
        email: 'admin@riverside.demo',
        path: linkFrom('/grades', /^\/results\/\d+$/),
    },
    {
        name: 'report-cards',
        email: 'teacher@riverside.demo',
        path: reportCardClass,
    },
    {
        name: 'report-card-ai-draft',
        email: 'teacher@riverside.demo',
        path: async (page) => {
            await page.goto(await reportCardClass(page));
            // A draft card: only drafts can take a comment (and an AI draft).
            const row = page
                .getByRole('row')
                .filter({
                    has: page.getByRole('cell', { name: 'Draft', exact: true }),
                })
                .first();
            const href = await row
                .getByRole('link')
                .first()
                .getAttribute('href', { timeout: 10_000 });
            return new URL(href ?? '/', page.url()).pathname;
        },
        then: async (page) => {
            await page
                .getByRole('button', { name: /^(Draft comment|New draft)$/ })
                .click();
            await expect(page.getByTestId('ai-draft')).toBeVisible({
                timeout: 30_000,
            });
        },
    },
    { name: 'fees-dashboard', email: 'admin@riverside.demo', path: '/fees' },
    { name: 'audit-log', email: 'admin@riverside.demo', path: '/audit-log' },
    {
        name: 'parent-portal',
        email: 'parent@riverside.demo',
        path: '/dashboard',
    },
    {
        name: 'parent-attendance-mobile',
        email: 'parent@riverside.demo',
        path: linkFrom('/dashboard', /^\/family\/children\/\d+\/attendance/),
        mobile: true,
        fullPage: true,
    },
    {
        name: 'german-dark',
        email: 'admin@gymnasium.demo',
        path: '/fees',
        colorScheme: 'dark',
    },
];

for (const shot of shots) {
    test(shot.name, async ({ browser }) => {
        test.setTimeout(120_000);
        const context = await browser.newContext({
            colorScheme: shot.colorScheme ?? 'light',
            viewport: shot.mobile
                ? { width: 390, height: 844 }
                : { width: 1440, height: 900 },
            deviceScaleFactor: shot.mobile ? 2 : 1,
            isMobile: shot.mobile ?? false,
            hasTouch: shot.mobile ?? false,
        });
        const page = await context.newPage();

        if (shot.email !== null) {
            await login(page, shot.email);
        }
        const path =
            typeof shot.path === 'string' ? shot.path : await shot.path(page);
        await page.goto(path);
        await page.waitForLoadState('networkidle');
        await shot.then?.(page);
        // Charts animate in.
        await page.waitForTimeout(1_500);

        await page.screenshot({
            path: `docs/screenshots/${shot.name}.png`,
            fullPage: shot.fullPage ?? false,
        });
        await context.close();
    });
}
