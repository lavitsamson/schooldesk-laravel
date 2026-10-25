import { getActiveLanguage } from 'laravel-vue-i18n';

/** Formats a number in the active language ("86.5" in English, "86,5" in German). */
export function formatNumber(
    value: number,
    options: Intl.NumberFormatOptions = { maximumFractionDigits: 1 },
): string {
    return new Intl.NumberFormat(getActiveLanguage(), options).format(value);
}

/** A rate such as attendance (0–100), or a dash when there is none. */
export function formatRate(value: number | null): string {
    return value === null ? '—' : `${formatNumber(value)} %`;
}
