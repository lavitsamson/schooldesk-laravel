import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

/*
 * Every main page, per role, in the light and the dark colour scheme:
 * - automated WCAG 2.1 A/AA checks (axe-core). What axe cannot check (keyboard flow, screen
 *   reader wording) is in the manual checklist in docs/ACCESSIBILITY.md;
 * - no Content Security Policy violations in the console (SecurityHeaders middleware), so a
 *   page that needs something the policy blocks fails here and not in production.
 * Runs against the demo seed and only reads, so it can run beside the other specs. Pages with
 * ids (a student, an invoice …) are found through the links on the list pages.
 */

type Path = string | ((page: Page) => Promise<string>);

const login = async (page: Page, email: string) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('DemoPass123!');
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
};

const resolve = async (page: Page, path: Path) =>
    typeof path === 'string' ? path : path(page);

/**
 * The first link on the page at `from` whose path (without the query) matches, e.g. a
 * student's page. The link is followed with its query.
 */
const linkFrom =
    (from: Path, pattern: RegExp): Path =>
    async (page) => {
        const source = await resolve(page, from);
        await page.goto(source);
        const urls = await page
            .locator('main a[href]')
            .evaluateAll((links) =>
                links.map((a) => (a as HTMLAnchorElement).href),
            );
        const url = urls
            .map((href) => new URL(href))
            .find((u) => pattern.test(u.pathname));
        if (url === undefined) {
            throw new Error(`No link matching ${pattern} on ${source}`);
        }
        return url.pathname + url.search;
    };

const roles: Record<string, { email: string | null; paths: Path[] }> = {
    guest: {
        email: null,
        paths: ['/', '/login', '/register', '/forgot-password', '/privacy'],
    },
    'school admin': {
        email: 'admin@riverside.demo',
        paths: [
            '/dashboard',
            '/students',
            '/students/create',
            linkFrom('/students', /^\/students\/\d+$/),
            '/guardians',
            '/imports',
            '/promotions/create',
            '/academic-years',
            '/classes',
            linkFrom('/classes', /^\/classes\/\d+$/),
            '/subjects',
            '/members',
            '/attendance',
            linkFrom('/attendance', /^\/attendance\/\d+\/report$/),
            '/grades',
            linkFrom('/grades', /^\/results\/\d+$/),
            '/grade-scale',
            '/report-cards',
            linkFrom('/report-cards', /^\/report-cards\/classes\/\d+/),
            '/fees',
            '/fees/structures',
            '/invoices',
            linkFrom('/invoices', /^\/invoices\/\d+$/),
            '/audit-log',
            '/data-protection',
            '/notifications',
            '/profile',
        ],
    },
    teacher: {
        email: 'teacher@riverside.demo',
        paths: [
            '/dashboard',
            '/attendance',
            linkFrom('/attendance', /^\/attendance\/\d+\/\d{4}-\d{2}-\d{2}$/),
            '/grades',
            linkFrom('/grades', /^\/grades\/\d+\/\d+$/),
            linkFrom(
                linkFrom('/grades', /^\/grades\/\d+\/\d+$/),
                /^\/assessments\/\d+\/grades$/,
            ),
            '/report-cards',
            linkFrom('/report-cards', /^\/report-cards\/classes\/\d+/),
        ],
    },
    parent: {
        email: 'parent@riverside.demo',
        paths: [
            '/dashboard',
            linkFrom('/dashboard', /^\/family\/children\/\d+\/attendance/),
            linkFrom('/dashboard', /^\/family\/children\/\d+\/results/),
            linkFrom('/dashboard', /^\/family\/children\/\d+\/report-cards/),
            '/family/invoices',
            '/notifications',
        ],
    },
};

for (const colorScheme of ['light', 'dark'] as const) {
    test.describe(`${colorScheme} mode`, () => {
        test.use({ colorScheme });

        for (const [role, { email, paths }] of Object.entries(roles)) {
            test(`pages for the ${role}: no WCAG 2.1 AA or CSP violations`, async ({
                page,
            }) => {
                test.setTimeout(15 * 60_000);
                const cspViolations: string[] = [];
                page.on('console', (message) => {
                    if (
                        message.type() === 'error' &&
                        /Content Security Policy/i.test(message.text())
                    ) {
                        cspViolations.push(message.text());
                    }
                });

                if (email !== null) {
                    await login(page, email);
                }

                for (const entry of paths) {
                    const path = await resolve(page, entry);
                    await page.goto(path);
                    await page.waitForLoadState('networkidle');

                    const { violations } = await new AxeBuilder({ page })
                        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
                        .analyze();

                    expect
                        .soft(
                            violations.map(
                                (v) =>
                                    `${v.impact} ${v.id}: ${v.help} — ${v.nodes
                                        .slice(0, 3)
                                        .map((n) => n.target.join(' '))
                                        .join(' | ')}`,
                            ),
                            `${path} (${colorScheme}): accessibility`,
                        )
                        .toEqual([]);
                    expect
                        .soft(cspViolations.splice(0), `${path}: CSP`)
                        .toEqual([]);
                }
            });
        }
    });
}
