import { getActiveLanguage } from 'laravel-vue-i18n';

/**
 * The locale for Intl formatting in the active language. English is British English
 * ("20 July 2026"), as in mails and PDFs (App\Support\LocalDate): the schools are in Europe.
 */
export function localeForIntl(): string {
    const language = getActiveLanguage();

    return language === 'en' ? 'en-GB' : language;
}
