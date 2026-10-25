import { localeForIntl } from '@/composables/locale';

/**
 * Percentages arrive as strings with two decimals ("86.50"), exactly as calculated on the
 * server. They are only formatted here, never recalculated.
 */
export function formatPercent(value: string | null): string {
    if (value === null) return '—';

    return `${Number(value).toLocaleString(localeForIntl(), {
        minimumFractionDigits: 1,
        maximumFractionDigits: 2,
    })} %`;
}

/** Marks without trailing zeros: "17.50" → "17.5". */
export function formatMarks(value: string | null): string {
    if (value === null) return '—';

    return Number(value).toLocaleString(localeForIntl(), {
        maximumFractionDigits: 2,
    });
}
