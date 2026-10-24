/**
 * Formats a YYYY-MM-DD date for display. Parsed as UTC and formatted in UTC, so the day never
 * shifts with the browser's timezone.
 */
export function formatDate(
    date: string,
    options: Intl.DateTimeFormatOptions = {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    },
): string {
    return new Intl.DateTimeFormat(undefined, {
        ...options,
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}
