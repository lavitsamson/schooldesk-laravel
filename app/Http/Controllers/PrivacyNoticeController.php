<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\Extension\Table\TableExtension;

/**
 * The public privacy notice, in the visitor's language (SetLocale). The text is a template in
 * resources/privacy/{locale}.md: each school completes the bracketed parts with its own details
 * and has them checked before going live (docs/GDPR.md).
 */
class PrivacyNoticeController extends Controller
{
    public function __invoke(): Response
    {
        $path = resource_path('privacy/'.App::getLocale().'.md');
        $markdown = (string) file_get_contents(is_file($path) ? $path : resource_path('privacy/en.md'));

        return Inertia::render('Privacy', [
            'html' => Str::markdown(str_replace(':app', (string) config('app.name'), $markdown), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ], [new TableExtension]),
        ]);
    }
}
