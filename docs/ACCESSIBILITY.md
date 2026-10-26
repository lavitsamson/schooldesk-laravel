# Accessibility

Target: WCAG 2.1 level AA, in English, German and Hindi, in the light and the dark colour scheme.

## Automated checks

`tests/e2e/pages.spec.ts` signs in as each role (guest, school admin, teacher, parent), opens every main page in both colour schemes and runs [axe-core](https://github.com/dequelabs/axe-core) with the WCAG 2.0/2.1 A and AA rules. Any violation fails the Playwright job in CI. The same walk fails on Content Security Policy violations (docs/SECURITY.md).

The first run (milestone 11) found, and the milestone fixed:

| Finding | Fix |
|---|---|
| Logo links with no accessible name (every page) | The logo is decorative (`aria-hidden`); the links are named "Dashboard" and "Home" |
| White on green and on light blue in the attendance grid: 3.3:1 and 4.1:1 | Darker shades, at least 5:1 |
| Day numbers in the parents' attendance calendar and disabled pagination links below 4.5:1 | Darker grey in light mode, lighter in dark mode |
| A link in running text (invoice list) told apart from the text by colour only | Underlined |
| The landing page was Laravel's scaffold, with low-contrast text and images from laravel.com | A SchoolDesk landing page |

## Manual checklist

What axe cannot judge, checked by hand:

- **Keyboard.** Every action works with the keyboard alone. A "Skip to main content" link is the first stop on every signed-in page. Focus is always visible (indigo focus rings). Dialogs (confirmations, "Add assessment") are native modal `<dialog>` elements, so focus stays inside, and Escape closes them.
- **The mark grid** works like a spreadsheet: Enter and the arrow keys move between students, and each cell is labelled "Marks for <student>".
- **The attendance grid** is one radio group per student, labelled with the student's name, so each status is announced together with whose it is.
- **Landmarks and headings.** Main navigation (labelled), page header, `main`, footer; one `h2` page title, sections with their own headings.
- **Forms.** Every field has a visible `<label>`. Validation errors appear as text next to the field. Known gap: they are not yet tied to the field with `aria-describedby`, so a screen reader reads them when it reaches them, not with the field.
- **Status messages** ("Attendance saved …", import progress) are in a `role="status"` region, announced without moving focus.
- **Charts** (attendance per day, fees per class) have a text summary as their accessible name, and the same numbers are in a table on the page.
- **Language.** `<html lang>` follows the user's language (en, de, hi), so screen readers switch voices.
- **Small screens.** Layouts are responsive (Tailwind breakpoints, a collapsible navigation), and wide tables scroll inside their own container instead of the page.
- **No information by colour alone.** Attendance and invoice states always have a word or letter (P/A/L/E) as well as a colour.
