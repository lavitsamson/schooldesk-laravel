<?php

declare(strict_types=1);

use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;

use function Knuckles\Scribe\Config\removeStrategies;

/*
 * Scribe generates the parent API docs as static files in public/docs (served at /docs):
 *
 *     php artisan scribe:generate
 *
 * Scribe is a dev dependency, so production installs have no generator: this file is then
 * empty instead of failing on Scribe's classes. Responses come from the @response examples in
 * the controllers, never from calling the API (no response calls against a database).
 */
if (! class_exists(Defaults::class)) {
    return [];
}

return [
    'title' => 'SchoolDesk Parent API',

    'description' => 'The parent portal as a JSON API for a mobile app: children, attendance, grades, report cards, invoices and notifications.',

    'intro_text' => <<<'INTRO'
            Parents sign in with the email address and password of their parent portal account and get a bearer token for one school (`POST /api/v1/auth/token`). Every request then acts for that school only.

            <aside>
            <strong>Conventions.</strong> Responses wrap their content in <code>data</code>. Lists are cursor-paginated (<code>?limit=</code>, 1–100, and <code>?cursor=</code> from <code>meta.next_cursor</code>). Amounts are integers in minor units (cents) of the school's currency (<code>GET /me</code>). Dates are <code>YYYY-MM-DD</code>, times ISO 8601.
            Errors always look like <code>{"message": "…", "code": "not_found"}</code>, with <code>errors</code> per field for <code>validation_failed</code> (422). Records of other schools are 404; another family's records 403. 60 requests a minute per token.
            </aside>

            Demo: sign in as <code>parent@riverside.demo</code> with the password <code>DemoPass123!</code>.
        INTRO,

    'base_url' => config('app.url'),

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/v1/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            'exclude' => [],
        ],
    ],

    'type' => 'static',

    'theme' => 'default',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => false,
        'docs_url' => '/docs',
        'assets_directory' => null,
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => [],
    ],

    'try_it_out' => [
        'enabled' => true,
        'base_url' => null,
        'use_csrf' => false,
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    'auth' => [
        'enabled' => true,
        'default' => true,
        'in' => 'bearer',
        'name' => 'Authorization',
        'use_value' => null,
        'placeholder' => '{YOUR_TOKEN}',
        'extra_info' => 'Get a token from <code>POST /api/v1/auth/token</code> and send it as <code>Authorization: Bearer {token}</code>.',
    ],

    'example_languages' => [
        'bash',
        'javascript',
    ],

    'postman' => [
        'enabled' => true,
        'overrides' => [],
    ],

    'openapi' => [
        'enabled' => true,
        'version' => '3.0.3',
        'overrides' => [
            'info.version' => '1.0.0',
        ],
        'generators' => [],
    ],

    'groups' => [
        'default' => 'Endpoints',
        'order' => ['Authentication', 'Account', 'Children', 'Invoices', 'Notifications'],
    ],

    'logo' => false,

    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        'faker_seed' => 1234,
        // Examples come from the @response annotations: no models are created or read.
        'models_source' => [],
    ],

    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        'responses' => removeStrategies(Defaults::RESPONSES_STRATEGIES, [
            Strategies\Responses\ResponseCalls::class,
        ]),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ],
    ],

    'database_connections_to_transact' => [],

    'fractal' => [
        'serializer' => null,
    ],
];
