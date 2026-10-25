// Fails when the app uses a translation key that lang/de.json or lang/hi.json lacks, or when
// a language file has keys nothing uses any more. Run with `npm run i18n:check`.
//
// Keys are the English strings (as with Laravel's __()), found as the first string argument
// of $t(), trans(), $tChoice() and transChoice() in resources/js, and of __() and
// trans_choice() in app/ and resources/views. Group keys such as 'auth.failed' live in
// lang/*/*.php and are not checked here. Placeholders are checked by Pest
// (tests/Unit/TranslationsTest.php).
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const locales = ['de', 'hi'];

const walk = (dir) =>
    fs
        .readdirSync(dir, { withFileTypes: true })
        .flatMap((entry) =>
            entry.isDirectory()
                ? walk(path.join(dir, entry.name))
                : [path.join(dir, entry.name)],
        );

const sources = [
    {
        dir: 'resources/js',
        files: /\.(vue|ts)$/,
        call: /(?:\$t|\btrans|\$tChoice|\btransChoice)\(\s*(['"`])((?:\\.|(?!\1).)*)\1/gs,
    },
    {
        dir: 'app',
        files: /\.php$/,
        call: /\b(?:__|trans_choice)\(\s*(['"])((?:\\.|(?!\1).)*)\1/gs,
    },
    {
        dir: 'resources/views',
        files: /\.blade\.php$/,
        call: /\b(?:__|trans_choice)\(\s*(['"])((?:\\.|(?!\1).)*)\1/gs,
    },
];

const groupKey = /^[a-z_]+\.[a-z_.]+$/;
const used = new Map();

for (const { dir, files, call } of sources) {
    for (const file of walk(path.join(root, dir)).filter((f) => files.test(f))) {
        for (const match of fs.readFileSync(file, 'utf8').matchAll(call)) {
            const key = match[2].replace(/\\(['"`\\])/g, '$1');
            if (!groupKey.test(key) && !used.has(key)) {
                used.set(key, path.relative(root, file));
            }
        }
    }
}

let failed = false;

for (const locale of locales) {
    const messages = JSON.parse(
        fs.readFileSync(path.join(root, `lang/${locale}.json`), 'utf8'),
    );

    for (const [key, file] of used) {
        if (!(key in messages)) {
            console.error(`lang/${locale}.json is missing "${key}" (${file})`);
            failed = true;
        }
    }

    for (const key of Object.keys(messages)) {
        if (!used.has(key)) {
            console.error(`lang/${locale}.json has an unused key "${key}"`);
            failed = true;
        }
    }
}

if (failed) process.exit(1);

console.log(`${used.size} keys translated in ${locales.join(', ')}.`);
