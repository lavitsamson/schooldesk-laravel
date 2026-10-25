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

];
