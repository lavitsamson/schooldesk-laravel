# ADR 0010: Localisation in English, German and Hindi

- **Status:** Accepted
- **Date:** 2026-10-26
- **Deciders:** Lavit Samson

## Context

SchoolDesk is used by schools in different countries. The UI, validation messages, emails,
notifications, exports and PDFs must be in English, German or Hindi. Everything a parent or
teacher reads must be in the same language.

What makes this harder:

- Text is produced in three places: Vue pages, PHP during a request, and PHP in queued jobs and
  scheduled commands, where there is no request, user or browser to ask.
- One school's people do not all share a language. A German school can have an English-speaking
  teacher, and a parent can have children at schools with different languages.
- Some text is stored (database notifications, import errors) and read later, perhaps by
  someone with another language.
- Dates and numbers differ per language: "26 October 2026" / "26. Oktober 2026", "86.5" /
  "86,5".
- dompdf's default font has no Devanagari, and dompdf does not shape complex scripts.
- Hindi translations are machine quality. No native speaker has reviewed them.

## Decision

1. **The English text is the key.** PHP uses `__('Student saved.')` and Vue uses
   `$t('Student saved.')`, with the translations in `lang/de.json` and `lang/hi.json`. The same
   files serve both sides (laravel-vue-i18n bundles them into the frontend). Code stays readable,
   and a missing translation shows English, not a key like `students.saved`. Laravel's own
   messages (validation, auth, passwords, pagination) keep their group files in
   `lang/{en,de,hi}/*.php`. Plurals use Laravel's `:count item|:count items` syntax on both sides.

2. **A check keeps the files complete.** `npm run i18n:check` (in CI's lint job) finds every
   key used in `resources/js`, `app/` and `resources/views` and fails when German or Hindi lacks
   one, or when a language file has keys nothing uses any more. A Pest test checks that each
   translation keeps the English text's placeholders (`:name`, `:count`). Because the English
   text is the key, changing it is a key change: the check makes the other files follow.

3. **The language of a request: user, then school, then browser.** `SetLocale` runs after
   `SetCurrentSchool` and picks the user's own choice (profile), else the current school's
   language, else the browser's `Accept-Language` among the three, else English. The language
   is shared with Inertia and can change without a full page load, for example after a school
   switch.

4. **Queued work runs in the school's language.** Jobs and scheduled commands carry a school id
   and run inside `CurrentSchool::runInSchoolLocale()`, which sets the school and its language
   together. Imports, report-card batches, receipts, invoice runs and reminders all use it. Mails
   to users follow `User::preferredLocale()`, so a user's own choice wins over the school's.
   Guardians without an account get the school's language. Invitations set their locale when they
   are created, because they are queued without a school context.

5. **Stored text is translated when read.** Database notifications store data (first name,
   date, invoice number), not sentences. `NotificationResource` builds the title for the reader,
   in the reader's language. When a whole import fails, its message is stored as the English key
   and translated by `ImportBatchResource`. Row errors in the error report are written in the
   school's language, like the rest of the import.

6. **PDFs use the school's language.** Report cards, invoices and receipts are documents of the
   school, rendered once and served to everyone, so they are in the school's language. That is
   also the language of AI comment drafts.

7. **One way to format dates and numbers.** On the server, `LocalDate` (Carbon's `isoFormat`)
   and `LocalNumber` (`NumberFormatter`). On the frontend, `Intl` with `localeForIntl()`.
   English uses British conventions (`en_GB` / `en-GB`, "26 October 2026") on both sides,
   because the schools are in Europe. Money is formatted by the `Money` value object (ADR 0008).

8. **Hindi PDFs use a bundled Devanagari font.** `PdfFont` adds Noto Sans Devanagari (SIL Open
   Font License, `resources/fonts`) for Hindi documents. It also covers Latin letters and digits,
   so names and amounts render too.

9. **Stripe Checkout in the payer's language.** Checkout gets the payer's locale. Stripe has no
   Hindi, so Hindi users get `auto` and Stripe picks from the browser.

## Consequences

- Adding a language means a JSON file, the four group files, a `Locale` case, its Carbon and
  `Intl` data, and possibly a PDF font. The i18n check lists every missing key.
- English text can't be edited freely: a new wording is a new key in every language. E2E tests
  also match English text.
- **dompdf does not shape Devanagari.** Most text is readable, but some vowel signs land in the
  wrong place: the i-matra (ि) is drawn after its consonant instead of before it, so "रिपोर्ट"
  shows as "रपिोर्ट", and some conjuncts render less neatly. Hindi report cards are
  usable but look less neat than the browser. Shaping needs a different renderer (headless
  Chromium, or wkhtmltopdf), which would add a large dependency to the Docker image. This can be
  revisited when a Hindi-speaking school uses SchoolDesk.
- **Hindi is machine quality.** The README says so. A native speaker should review
  `lang/hi.json` and `lang/hi/*.php` before a Hindi-speaking school goes live.
- A user's language choice changes their screens and mails, not the school's documents. A
  German teacher at an English school reads the UI in German and prints report cards in English.

## Alternatives considered

- **Short keys (`students.saved`) in PHP group files.** This is common in Laravel, but code
  becomes harder to read, missing translations show keys, and the frontend would need a second
  format. With English keys, both sides use the same JSON files.
- **Lazy-loading the language files on the frontend.** Three small files are bundled eagerly, so
  the page never shows English first and then switches.
- **The reader's language for PDFs.** Every reader would need their own rendered copy, and a
  report card is an official document the school signs off once in its own language.
- **Storing translated notification text.** It would freeze the text in the sender's language
  and break when a user changes their language.
- **A headless browser for PDFs** (Browsershot, Gotenberg) to shape Hindi correctly. Rejected for
  now: it adds Chromium or a separate service to the image for one language's PDFs, and dompdf
  is fast and simple for the other two.
