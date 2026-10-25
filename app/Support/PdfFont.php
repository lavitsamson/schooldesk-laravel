<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\App;

/**
 * The font of PDFs in the active language. dompdf's DejaVu Sans has no Devanagari, so Hindi
 * documents use the bundled Noto Sans Devanagari (SIL Open Font License, resources/fonts),
 * which also covers Latin letters and digits for names and amounts. dompdf does not shape
 * complex scripts, so some Hindi conjuncts render less neatly than in a browser (ADR 0010).
 */
final class PdfFont
{
    /** CSS to put in the document's <style>: @font-face rules plus a body font-family. */
    public static function css(): string
    {
        if (App::getLocale() !== 'hi') {
            return 'body { font-family: "DejaVu Sans", sans-serif; }';
        }

        $face = fn (string $file, string $weight): string => sprintf(
            '@font-face { font-family: "Noto Sans Devanagari"; font-weight: %s; src: url("%s"); }',
            $weight,
            resource_path("fonts/{$file}"),
        );

        return implode("\n", [
            $face('NotoSansDevanagari-Regular.ttf', 'normal'),
            $face('NotoSansDevanagari-Bold.ttf', 'bold'),
            'body { font-family: "Noto Sans Devanagari", "DejaVu Sans", sans-serif; }',
        ]);
    }
}
