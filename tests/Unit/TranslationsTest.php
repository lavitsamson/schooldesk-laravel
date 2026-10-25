<?php

declare(strict_types=1);

/**
 * The JSON files are keyed by the English text; German and Hindi must translate the same
 * keys and keep every :placeholder, or a name or count silently disappears from the text.
 */
function jsonTranslations(string $locale): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 2)."/lang/{$locale}.json"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return list<string>
 */
function translationPlaceholders(string $text): array
{
    preg_match_all('/:([a-z_]+)/i', $text, $matches);

    return array_values(array_unique(array_map('strtolower', $matches[1])));
}

test('German and Hindi translate the same keys', function (): void {
    expect(array_keys(jsonTranslations('hi')))->toEqualCanonicalizing(array_keys(jsonTranslations('de')));
});

test('translations keep every placeholder', function (string $locale): void {
    foreach (jsonTranslations($locale) as $english => $translated) {
        $expected = translationPlaceholders($english);
        sort($expected);
        $actual = translationPlaceholders($translated);
        sort($actual);

        expect($actual)->toBe($expected, "{$locale}: \"{$english}\"");
    }
})->with(['de', 'hi']);

test('plural translations have as many forms as the English key', function (string $locale): void {
    foreach (jsonTranslations($locale) as $english => $translated) {
        expect(substr_count($translated, '|'))->toBe(substr_count($english, '|'), "{$locale}: \"{$english}\"");
    }
})->with(['de', 'hi']);

test('framework language files have every English key', function (string $locale, string $group): void {
    $flatten = function (array $lines, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($lines as $key => $value) {
            $keys = is_array($value) && $key !== 'attributes'
                ? [...$keys, ...$flatten($value, "{$prefix}{$key}.")]
                : [...$keys, "{$prefix}{$key}"];
        }

        return $keys;
    };
    $load = fn (string $lang): array => require dirname(__DIR__, 2)."/lang/{$lang}/{$group}.php";

    expect($flatten($load($locale)))->toEqualCanonicalizing($flatten($load('en')));
})->with(['de', 'hi'])->with(['auth', 'pagination', 'passwords', 'validation']);

test('translations are not empty', function (string $locale): void {
    foreach (jsonTranslations($locale) as $english => $translated) {
        expect(trim($translated))->not->toBe('', "{$locale}: \"{$english}\"");
    }
})->with(['de', 'hi']);
