import { usePage } from '@inertiajs/vue3';
import { getActiveLanguage } from 'laravel-vue-i18n';

/** Currencies without minor units (as on the server, App\Support\Money). */
const ZERO_DECIMAL = new Set([
    'BIF',
    'CLP',
    'DJF',
    'GNF',
    'JPY',
    'KMF',
    'KRW',
    'MGA',
    'PYG',
    'RWF',
    'UGX',
    'VND',
    'VUV',
    'XAF',
    'XOF',
    'XPF',
]);

/**
 * Amounts arrive as integers in minor units (cents) and are only formatted here, in the
 * school's currency and language, never calculated with.
 */
export function formatMoney(
    minor: number,
    currency: string,
    locale: string,
): string {
    const decimals = ZERO_DECIMAL.has(currency) ? 0 : 2;

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
    }).format(minor / 10 ** decimals);
}

/** formatMoney() with the current school's currency, in the active language. */
export function useMoney() {
    const page = usePage();

    return (minor: number): string =>
        formatMoney(
            minor,
            page.props.school?.currency ?? 'EUR',
            getActiveLanguage(),
        );
}
