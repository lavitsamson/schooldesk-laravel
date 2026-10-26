<?php

declare(strict_types=1);

return [

    'invitations' => [
        'expire_after_days' => (int) env('INVITATION_EXPIRE_DAYS', 7),
    ],

    'imports' => [
        // Rows per queued chunk job.
        'chunk_size' => (int) env('IMPORT_CHUNK_SIZE', 200),
        'max_file_kb' => (int) env('IMPORT_MAX_FILE_KB', 5120),
        // Import batches and their error reports hold personal data and are pruned after this.
        'keep_days' => (int) env('IMPORT_KEEP_DAYS', 30),
    ],

    'attendance' => [
        // Absence mails wait this long, so a wrong mark corrected straight away is never sent.
        'absence_notification_delay_minutes' => (int) env('ABSENCE_NOTIFICATION_DELAY_MINUTES', 10),
    ],

    'report_cards' => [
        // Teacher comments are kept short enough for one page.
        'comment_max_length' => 1200,
        // AI comment drafts per school and hour (all teachers together).
        'ai_drafts_per_hour' => (int) env('AI_DRAFTS_PER_HOUR', 100),
    ],

    'fees' => [
        // The daily run invoices terms that start within this many days.
        'generate_days_ahead' => (int) env('FEES_GENERATE_DAYS_AHEAD', 14),
        // Overdue reminders go out this many days after the due date (once each).
        'reminder_days' => [3, 10],
        // Invoice numbers: SD-2026-000123.
        'invoice_prefix' => env('FEES_INVOICE_PREFIX', 'SD'),
    ],

    'gdpr' => [
        // Student data exports: the emailed download link and the ZIP itself expire after this.
        'export_link_hours' => (int) env('GDPR_EXPORT_LINK_HOURS', 24),
        // Choices for how long attendance notes (often health reasons) are kept, in months.
        'attendance_note_retention_months' => [6, 12, 24, 36],
    ],

    'api' => [
        // Parent API tokens (one per device and school) expire after this many days.
        'token_days' => (int) env('API_TOKEN_DAYS', 90),
        // Requests per minute and token (or IP address, before signing in).
        'requests_per_minute' => (int) env('API_REQUESTS_PER_MINUTE', 60),
        // Sign-in attempts per minute and email address plus IP address.
        'token_attempts_per_minute' => 5,
        // Cursor pagination: default and largest page size.
        'per_page' => 25,
        'max_per_page' => 100,
    ],

];
