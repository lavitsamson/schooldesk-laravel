import { getActiveLanguage } from 'laravel-vue-i18n';

/**
 * Formats a YYYY-MM-DD date for display in the active language. Parsed as UTC and formatted in
 * UTC, so the day never shifts with the browser's timezone.
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
    return new Intl.DateTimeFormat(getActiveLanguage(), {
        ...options,
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}

/**
 * Formats an ISO 8601 timestamp (e.g. when something was approved) in the browser's timezone.
 */
export function formatDateTime(timestamp: string): string {
    return new Intl.DateTimeFormat(getActiveLanguage(), {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(timestamp));
}
