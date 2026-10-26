#!/usr/bin/env node
// Response times of the main pages against the performance dataset (README: "Performance").
//
//   php artisan db:seed --class=PerformanceSeeder
//   node scripts/benchmark.mjs [--base=http://127.0.0.1:8001] [--runs=30] [--school=perf-01] [--no-batch]
//
// Signs in as the school's admin, discovers a class and a student, then requests each page
// --runs times (after 3 warm-up requests) the way the app navigates: Inertia JSON requests,
// one at a time. Prints p50, p95 and max per page and checks the targets from the spec.
// Finally generates the report cards of a class (queued; Horizon must run) and times the batch.
// Exits with 1 when a target is missed.

const args = Object.fromEntries(
    process.argv.slice(2).map((arg) => {
        const [key, value] = arg.replace(/^--/, '').split('=');
        return [key, value ?? true];
    }),
);
const base = String(args.base ?? 'http://127.0.0.1:8001');
const runs = Number(args.runs ?? 30);
const school = String(args.school ?? 'perf-01');
const email = `admin@${school}.test`;
const password = 'DemoPass123!';

const cookies = new Map();
let version = '';

function remember(response) {
    for (const cookie of response.headers.getSetCookie()) {
        const [pair] = cookie.split(';');
        const index = pair.indexOf('=');
        cookies.set(pair.slice(0, index), pair.slice(index + 1));
    }
}

function headers(extra = {}) {
    return {
        Cookie: [...cookies].map(([k, v]) => `${k}=${v}`).join('; '),
        'X-XSRF-TOKEN': decodeURIComponent(cookies.get('XSRF-TOKEN') ?? ''),
        'X-Requested-With': 'XMLHttpRequest',
        ...extra,
    };
}

async function request(path, options = {}) {
    const response = await fetch(base + path, {
        redirect: 'manual',
        ...options,
        headers: headers(options.headers),
    });
    remember(response);
    return response;
}

/** An Inertia visit: the page component and its props as JSON. */
async function visit(path, only = null) {
    const response = await request(path, {
        headers: {
            Accept: 'text/html, application/xhtml+xml',
            'X-Inertia': 'true',
            'X-Inertia-Version': version,
            ...(only ? { 'X-Inertia-Partial-Component': only.component, 'X-Inertia-Partial-Data': only.props } : {}),
        },
    });
    if (response.status !== 200) {
        throw new Error(`GET ${path}: HTTP ${response.status}`);
    }
    return response.json();
}

async function signIn() {
    const html = await (await request('/login')).text();
    version = (html.match(/&quot;version&quot;:&quot;([^&]*)&quot;/) ?? [])[1] ?? '';

    const response = await request('/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email, password }),
    });
    if (![302, 303, 204].includes(response.status)) {
        throw new Error(`Sign-in as ${email} failed: HTTP ${response.status}. Seeded the performance data?`);
    }
}

function percentile(sorted, p) {
    return sorted[Math.min(sorted.length - 1, Math.ceil((p / 100) * sorted.length) - 1)];
}

async function measure(label, path, target = null) {
    for (let i = 0; i < 3; i++) {
        await visit(path);
    }
    const times = [];
    for (let i = 0; i < runs; i++) {
        const started = performance.now();
        await visit(path);
        times.push(performance.now() - started);
    }
    times.sort((a, b) => a - b);
    const p95 = percentile(times, 95);
    return {
        label,
        path,
        p50: percentile(times, 50),
        p95,
        max: times.at(-1),
        target,
        ok: target === null || p95 < target,
    };
}

/** The last weekday before today: a day with attendance in the seeded data. */
function lastSchoolDay() {
    const day = new Date();
    do {
        day.setDate(day.getDate() - 1);
    } while (day.getDay() === 0 || day.getDay() === 6);
    return day.toISOString().slice(0, 10);
}

async function timeReportCardBatch(classId) {
    const page = await visit(`/report-cards/classes/${classId}`);
    const term = page.props.term.id;
    const started = performance.now();

    const response = await request(`/report-cards/classes/${classId}/generate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Inertia': 'true', 'X-Inertia-Version': version },
        body: JSON.stringify({ term }),
    });
    if (![302, 303].includes(response.status)) {
        throw new Error(`Generating report cards failed: HTTP ${response.status}`);
    }

    for (;;) {
        const { props } = await visit(`/report-cards/classes/${classId}?term=${term}`, {
            component: page.component,
            props: 'batch',
        });
        if (props.batch?.is_finished) {
            return { seconds: (performance.now() - started) / 1000, ...props.batch };
        }
        if (performance.now() - started > 600_000) {
            throw new Error('The batch did not finish within 10 minutes: is Horizon running?');
        }
        await new Promise((resolve) => setTimeout(resolve, 250));
    }
}

await signIn();

const students = await visit('/students');
const studentId = students.props.students.data[0].id;
const attendance = await visit('/attendance');
const classId = attendance.props.classes[0].id;
const day = lastSchoolDay();

const results = [];
for (const [label, path, target] of [
    ['Dashboard', '/dashboard'],
    ['Student list', '/students', 200],
    ['Student list, search', '/students?search=mei', 200],
    ['Student list, by class', '/students?sort=class&direction=desc', 200],
    ['Student profile', `/students/${studentId}`],
    ['Attendance grid', `/attendance/${classId}/${day}`, 150],
    ['Attendance month report', `/attendance/${classId}/report`],
    ['Class results', `/results/${classId}`],
    ['Fees dashboard', '/fees'],
    ['Invoice list', '/invoices'],
    ['Audit log', '/audit-log'],
]) {
    results.push(await measure(label, path, target ?? null));
}

const ms = (value) => `${value.toFixed(0)} ms`;
console.log(`\n${school}: ${runs} requests per page, ${base}\n`);
console.log('| Page | p50 | p95 | max | target (p95) |');
console.log('|---|---:|---:|---:|---|');
for (const r of results) {
    const target = r.target === null ? '' : `< ${r.target} ms ${r.ok ? '✓' : '✗'}`;
    console.log(`| ${r.label} | ${ms(r.p50)} | ${ms(r.p95)} | ${ms(r.max)} | ${target} |`);
}

let batchOk = true;
if (!args['no-batch']) {
    const batch = await timeReportCardBatch(classId);
    batchOk = batch.seconds < 60 && batch.failed === 0;
    console.log(
        `\nReport cards for a class of ${batch.total}: ${batch.seconds.toFixed(1)} s, ${batch.failed} failed (target < 60 s ${batchOk ? '✓' : '✗'})`,
    );
}

process.exit(results.every((r) => r.ok) && batchOk ? 0 : 1);
