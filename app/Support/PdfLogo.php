<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * A school logo for dompdf, inlined as a data URI: dompdf cannot read the private disk, and
 * remote loading stays off.
 */
final class PdfLogo
{
    public static function dataUri(?string $path, string $disk = 'local'): ?string
    {
        $storage = Storage::disk($disk);

        if ($path === null || ! $storage->exists($path)) {
            return null;
        }

        $mime = $storage->mimeType($path);

        return in_array($mime, ['image/png', 'image/jpeg'], true)
            ? "data:{$mime};base64,".base64_encode((string) $storage->get($path))
            : null;
    }
}
