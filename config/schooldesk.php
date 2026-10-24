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

];
